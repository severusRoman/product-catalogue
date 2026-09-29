#!/bin/bash
# End-to-end test script for Shelfwise (PHP + MySQL).
#
# Usage (local only, it RESETS the database!):
#   1. Configure config/config.php and create an empty database
#   2. php -S 127.0.0.1:8000 -t .            (in a second terminal)
#   3. bash tests/smoke_test.sh
#
# Environment variables: BASE (default http://127.0.0.1:8000), DB_NAME, DB_USER, DB_PASS

BASE=${BASE:-http://127.0.0.1:8000}
DB_NAME=${DB_NAME:-catalogue_db}
DB_USER=${DB_USER:-cat_user}
DB_PASS=${DB_PASS:-cat_pass}
ROOT=$(cd "$(dirname "$0")/.." && pwd)
TMP=$(mktemp -d)
PASS=0; FAIL=0

ok()  { PASS=$((PASS+1)); printf "  PASS  %s\n" "$1"; }
bad() { FAIL=$((FAIL+1)); printf "  FAIL  %s  -> %s\n" "$1" "$2"; }
section() { printf "\n== %s ==\n" "$1"; }
has()     { if printf '%s' "$2" | grep -qF -- "$3"; then ok "$1"; else bad "$1" "expected to find: $3"; fi; }
hasnt()   { if printf '%s' "$2" | grep -qF -- "$3"; then bad "$1" "should NOT contain: $3"; else ok "$1"; fi; }
eq()      { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "got '$2', expected '$3'"; fi; }
sql()     { mysql -N -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>/dev/null; }
token()   { curl -s -b "$1" -c "$1" "$BASE/$2" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//'; }
cards()   { printf '%s' "$1" | grep -c '<article class="card">'; }
first_title() { printf '%s' "$1" | grep -o 'class="card__title"><a href="[^"]*">[^<]*' | head -1 | sed 's/.*">//'; }

login() { # jar email password -> "status redirect"
    local t; t=$(token "$1" login.php)
    curl -s -b "$1" -c "$1" -o /dev/null -w "%{http_code} %{redirect_url}" -X POST \
        --data-urlencode "csrf_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/login.php"
}

# --- fixtures ------------------------------------------------------------------
echo "Resetting test database..."
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$ROOT/database/schema.sql" 2>/dev/null
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$ROOT/database/seed_demo.sql" 2>/dev/null
# tiny valid PNG, a fake "image" that is really PHP code, and an oversized file
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/ok.png"
printf '<?php echo "pwned"; ?>' > "$TMP/evil.jpg"
head -c 3200000 /dev/urandom > "$TMP/big.png"

# =============================================================================
section "Catalogue: search, filter, sort, pagination"
H=$(curl -s "$BASE/")
has "T01 Home page loads with latest products" "$H" "Latest arrivals"

R=$(curl -s "$BASE/catalogue.php?q=honey")
eq  "T02 Search 'honey' returns exactly 1 product" "$(cards "$R")" "1"
has "T02b ...and it is the honey product" "$R" "Sundarban Honey"

R=$(curl -s "$BASE/catalogue.php?q=zzzznotfound")
has "T03 Search with no match shows empty state" "$R" "No products match"

R=$(curl -s "$BASE/catalogue.php?category=1")
eq  "T04 Category filter (Electronics) returns 5 products" "$(cards "$R")" "5"

R=$(curl -s "$BASE/catalogue.php?min=300&max=500")
eq  "T05 Price range 300-500 returns 4 products" "$(cards "$R")" "4"
R=$(curl -s "$BASE/catalogue.php?min=500&max=300")
eq  "T05b Reversed min/max is corrected (still 4 products)" "$(cards "$R")" "4"

R=$(curl -s "$BASE/catalogue.php?sort=price_asc")
eq  "T06 Sort by price ascending shows cheapest first" "$(first_title "$R")" "Gel Pen Set (12 colours)"

R=$(curl -s "$BASE/catalogue.php?in_stock=1&q=Canvas")
has "T07 'In stock only' hides out-of-stock Canvas Backpack" "$R" "No products match"

R=$(curl -s "$BASE/catalogue.php?page=3")
eq  "T08 Page 3 of 9-per-page listing shows the last 4 products" "$(cards "$R")" "4"
R=$(curl -s "$BASE/catalogue.php?page=999")
eq  "T08b Out-of-range page number is clamped, not an error" "$(cards "$R")" "4"

CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/catalogue.php?q=%27%20OR%20%271%27%3D%271%27%3B%20DROP%20TABLE%20products%3B--")
eq  "T09 SQL injection string in search does not cause an error" "$CODE" "200"
eq  "T09b ...and the products table still exists" "$(sql 'SELECT COUNT(*) FROM products')" "22"
R=$(curl -s "$BASE/catalogue.php?q=%25")
has "T09c A literal % in search is treated as text, not a wildcard" "$R" "No products match"

eq  "T10 Missing product returns 404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/product.php?id=99999")" "404"
eq  "T10b Non-numeric product id returns 404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/product.php?id=abc")" "404"

# =============================================================================
section "Registration"
J="$TMP/newuser.jar"
T=$(token "$J" register.php)
R=$(curl -s -b "$J" -c "$J" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "name=Test User" \
    --data-urlencode "email=test@example.com" --data-urlencode "password=short" --data-urlencode "password_confirm=short" "$BASE/register.php")
has "T11 Weak password (too short) is rejected" "$R" "Use at least 8 characters"
eq  "T11b ...and no account was created" "$(sql "SELECT COUNT(*) FROM users WHERE email='test@example.com'")" "0"

R=$(curl -s -b "$J" -c "$J" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "name=Test User" \
    --data-urlencode "email=not-an-email" --data-urlencode "password=Passw0rd123" --data-urlencode "password_confirm=Passw0rd123" "$BASE/register.php")
has "T12 Invalid email format is rejected" "$R" "Enter a valid email address"

R=$(curl -s -b "$J" -c "$J" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "name=Test User" \
    --data-urlencode "email=test@example.com" --data-urlencode "password=Passw0rd123" --data-urlencode "password_confirm=Different123" "$BASE/register.php")
has "T13 Mismatched password confirmation is rejected" "$R" "do not match"

OUT=$(curl -s -b "$J" -c "$J" -o /dev/null -w "%{http_code} %{redirect_url}" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "name=Test User" \
    --data-urlencode "email=Test@Example.com" --data-urlencode "password=Passw0rd123" --data-urlencode "password_confirm=Passw0rd123" "$BASE/register.php")
has "T14 Valid registration redirects to dashboard" "$OUT" "302 $BASE/dashboard.php"
HASH=$(sql "SELECT password_hash FROM users WHERE email='test@example.com'")
has "T14b Password is stored as a bcrypt hash, not plain text" "$HASH" '$2y$'
hasnt "T14c ...and the plain password is not in the database" "$HASH" "Passw0rd123"

JD="$TMP/dup.jar"; T=$(token "$JD" register.php)
R=$(curl -s -b "$JD" -c "$JD" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "name=Copy Cat" \
    --data-urlencode "email=test@example.com" --data-urlencode "password=Passw0rd123" --data-urlencode "password_confirm=Passw0rd123" "$BASE/register.php")
has "T15 Duplicate email is rejected" "$R" "already exists"

# =============================================================================
section "Login, logout and sessions"
eq  "T16 Guest visiting dashboard is redirected to login" \
    "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/dashboard.php")" "302 $BASE/login.php?next=dashboard.php"

JW="$TMP/wrong.jar"; T=$(token "$JW" login.php)
R=$(curl -s -b "$JW" -c "$JW" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "email=demo@shelfwise.test" --data-urlencode "password=WrongPass1" "$BASE/login.php")
has "T17 Wrong password shows a generic error" "$R" "do not match an account"
R=$(curl -s -b "$JW" -c "$JW" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "email=nobody@shelfwise.test" --data-urlencode "password=WrongPass1" "$BASE/login.php")
has "T17b Unknown email shows the SAME message (no user enumeration)" "$R" "do not match an account"

JDEMO="$TMP/demo.jar"
eq  "T18 Correct login redirects to dashboard" "$(login "$JDEMO" demo@shelfwise.test 'Demo@1234')" "302 $BASE/dashboard.php"
COOKIE=$(curl -s -D - -o /dev/null "$BASE/login.php" | grep -i '^set-cookie')
has "T19 Session cookie is HttpOnly" "$COOKIE" "HttpOnly"
has "T19b Session cookie has SameSite=Lax" "$COOKIE" "SameSite=Lax"

JO="$TMP/open.jar"
eq  "T20 Open-redirect attempt via ?next= is ignored" \
    "$(T=$(token "$JO" 'login.php?next=https%3A%2F%2Fevil.example'); curl -s -b "$JO" -c "$JO" -o /dev/null -w '%{http_code} %{redirect_url}' -X POST --data-urlencode "csrf_token=$T" --data-urlencode 'next=https://evil.example' --data-urlencode 'email=demo@shelfwise.test' --data-urlencode 'password=Demo@1234' "$BASE/login.php")" \
    "302 $BASE/dashboard.php"

eq  "T21 GET /logout.php does NOT log the user out" \
    "$(curl -s -b "$JDEMO" -c "$JDEMO" -o /dev/null -w '%{http_code}' "$BASE/logout.php"; curl -s -b "$JDEMO" -o /dev/null -w ' %{http_code}' "$BASE/dashboard.php")" "302 200"
T=$(token "$JDEMO" dashboard.php)
curl -s -b "$JDEMO" -c "$JDEMO" -o /dev/null -X POST --data-urlencode "csrf_token=$T" "$BASE/logout.php"
eq  "T21b POST logout ends the session (dashboard is protected again)" "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' "$BASE/dashboard.php")" "302"

JB="$TMP/brute.jar"
for i in 1 2 3 4 5; do login "$JB" admin@shelfwise.test "BadGuess$i" >/dev/null; done
T=$(token "$JB" login.php)
R=$(curl -s -b "$JB" -c "$JB" -X POST --data-urlencode "csrf_token=$T" --data-urlencode "email=admin@shelfwise.test" --data-urlencode "password=Admin@1234" "$BASE/login.php")
has "T22 Account is locked for 15 min after 5 failed logins (even with the right password)" "$R" "Too many failed attempts"
sql "DELETE FROM login_attempts" >/dev/null

# =============================================================================
section "CRUD and access control"
JDEMO="$TMP/demo2.jar"; login "$JDEMO" demo@shelfwise.test 'Demo@1234' >/dev/null
R=$(curl -s -b "$JDEMO" "$BASE/dashboard.php")
eq  "T23 Dashboard lists only the demo user's own products (10)" "$(printf '%s' "$R" | grep -c 'class="table__name"')" "10"
hasnt "T23b ...and does not show another user's product" "$R" "Wireless Bluetooth Headphones"

T=$(token "$JDEMO" product_form.php)
OUT=$(curl -s -b "$JDEMO" -c "$JDEMO" -o /dev/null -w "%{http_code} %{redirect_url}" -X POST "$BASE/product_form.php" \
    -F "csrf_token=$T" -F "name=Test Speaker" -F "category_id=1" -F "price=1999.50" -F "stock=7" -F "description=Loud and clear." -F "image=@$TMP/ok.png;type=image/png")
NEW_ID=$(printf '%s' "$OUT" | grep -o 'id=[0-9]*' | cut -d= -f2)
has "T24 CREATE product with image redirects to the new product page" "$OUT" "302 $BASE/product.php?id="
IMG=$(sql "SELECT image_path FROM products WHERE id=$NEW_ID")
[ -f "$ROOT/$IMG" ] && ok "T24b Uploaded image saved on disk under a random filename ($IMG)" || bad "T24b image saved" "not found: $IMG"
R=$(curl -s "$BASE/product.php?id=$NEW_ID")
has "T25 READ: new product page shows the saved details" "$R" "Test Speaker"
has "T25b ...formatted price" "$R" "1,999.50"

R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" -F "name=A" -F "category_id=99" -F "price=-5" -F "stock=abc")
has "T26 Invalid name rejected" "$R" "between 2 and 120 characters"
has "T26b Invalid category rejected" "$R" "Choose a category from the list"
has "T26c Negative price rejected" "$R" "Enter a price like"
has "T26d Non-numeric stock rejected" "$R" "whole number"

R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" -F "name=Zero Price" -F "category_id=1" -F "price=0" -F "stock=1")
has "T27 Edge case: price of 0 rejected" "$R" "greater than zero"
R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" -F "name=Too Many Decimals" -F "category_id=1" -F "price=10.999" -F "stock=1")
has "T27b Edge case: price with 3 decimals rejected" "$R" "Enter a price like"

R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" -F "name=Sneaky Upload" -F "category_id=1" -F "price=10" -F "stock=1" -F "image=@$TMP/evil.jpg;type=image/jpeg")
has "T28 A PHP script renamed to .jpg is rejected (real file type is checked)" "$R" "Upload a JPG, PNG or WebP image"
eq  "T28b ...and no product was created" "$(sql "SELECT COUNT(*) FROM products WHERE name='Sneaky Upload'")" "0"
R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" -F "name=Huge Upload" -F "category_id=1" -F "price=10" -F "stock=1" -F "image=@$TMP/big.png;type=image/png")
has "T29 Image over 2 MB is rejected" "$R" "too large"

# --form-string is used because curl's -F treats a leading "<" as "read this value from a file"
R=$(curl -s -b "$JDEMO" -c "$JDEMO" -X POST "$BASE/product_form.php" -F "csrf_token=$T" --form-string "name=<script>alert(1)</script>" -F "category_id=1" -F "price=10" -F "stock=1" --form-string "description=<img src=x onerror=alert(2)>" -o /dev/null -w "%{redirect_url}")
XSS_ID=$(printf '%s' "$R" | grep -o 'id=[0-9]*' | cut -d= -f2)
[ -n "$XSS_ID" ] && ok "T30a Product with HTML in its name/description is accepted as plain text" || bad "T30a XSS product created" "product was not created"
R=$(curl -s "$BASE/product.php?id=$XSS_ID")
has   "T30 XSS: <script> in a product name is displayed as harmless text" "$R" "&lt;script&gt;alert(1)&lt;/script&gt;"
hasnt "T30b ...and is never output as a real tag" "$R" "<script>alert(1)"
hasnt "T30c ...HTML in the description is escaped too" "$R" "<img src=x"

OUT=$(curl -s -b "$JDEMO" -c "$JDEMO" -o /dev/null -w "%{http_code} %{redirect_url}" -X POST "$BASE/product_form.php?id=$NEW_ID" \
    -F "csrf_token=$T" -F "id=$NEW_ID" -F "name=Test Speaker Pro" -F "category_id=1" -F "price=2499" -F "stock=0" -F "description=Updated." -F "remove_image=1")
has "T31 UPDATE product redirects back to its page" "$OUT" "302 $BASE/product.php?id=$NEW_ID"
eq  "T31b ...new name and price saved" "$(sql "SELECT CONCAT(name,'|',price,'|',stock) FROM products WHERE id=$NEW_ID")" "Test Speaker Pro|2499.00|0"
[ ! -f "$ROOT/$IMG" ] && ok "T31c Removed image is deleted from disk" || bad "T31c image cleanup" "file still exists"

# access control: demo user vs admin's product (id 1)
eq  "T32 ACCESS CONTROL: user cannot open edit form for someone else's product (403)" "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' "$BASE/product_form.php?id=1")" "403"
eq  "T32b ...and cannot save changes to it (403)" \
    "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' -X POST "$BASE/product_form.php?id=1" -F "csrf_token=$T" -F "id=1" -F "name=Hacked" -F "category_id=1" -F "price=1" -F "stock=1")" "403"
eq  "T32c ...and cannot delete it (403)" \
    "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' -X POST "$BASE/product_delete.php" --data-urlencode "csrf_token=$T" --data-urlencode "id=1")" "403"
eq  "T32d ...and the product is unchanged in the database" "$(sql 'SELECT name FROM products WHERE id=1')" "Wireless Bluetooth Headphones"
eq  "T33 Guest cannot open the add-product form (redirected to login)" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/product_form.php")" "302"

R=$(curl -s "$BASE/product.php?id=1"); hasnt "T34 Guests do not see Edit/Delete buttons" "$R" "Delete product"
R=$(curl -s -b "$JDEMO" "$BASE/product.php?id=$NEW_ID"); has "T34b Owner sees Edit/Delete buttons on own product" "$R" "Delete product"

# CSRF
eq  "T35 CSRF: delete request WITHOUT a token is refused (419)" "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' -X POST "$BASE/product_delete.php" --data-urlencode "id=$NEW_ID")" "419"
eq  "T35b CSRF: delete with a forged token is refused (419)" "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' -X POST "$BASE/product_delete.php" --data-urlencode "csrf_token=forged" --data-urlencode "id=$NEW_ID")" "419"
eq  "T35c ...product still exists" "$(sql "SELECT COUNT(*) FROM products WHERE id=$NEW_ID")" "1"
eq  "T35d GET request to the delete script does not delete anything" "$(curl -s -b "$JDEMO" -o /dev/null -w '%{http_code}' "$BASE/product_delete.php?id=$NEW_ID")" "302"
eq  "T35e ...(still exists)" "$(sql "SELECT COUNT(*) FROM products WHERE id=$NEW_ID")" "1"

T=$(token "$JDEMO" dashboard.php)
OUT=$(curl -s -b "$JDEMO" -c "$JDEMO" -o /dev/null -w "%{http_code} %{redirect_url}" -X POST "$BASE/product_delete.php" --data-urlencode "csrf_token=$T" --data-urlencode "id=$NEW_ID")
eq  "T36 DELETE own product succeeds" "$OUT" "302 $BASE/dashboard.php"
eq  "T36b ...row removed from database" "$(sql "SELECT COUNT(*) FROM products WHERE id=$NEW_ID")" "0"

JA="$TMP/admin.jar"; login "$JA" admin@shelfwise.test 'Admin@1234' >/dev/null
R=$(curl -s -b "$JA" "$BASE/dashboard.php")
has "T37 Admin dashboard shows the 'Listed by' column (all users' products)" "$R" "Listed by"
has "T37c Admin dashboard is paginated (10 per page)" "$R" 'aria-label="Pagination"'
eq  "T37b Admin can open edit form for another user's product" "$(curl -s -b "$JA" -o /dev/null -w '%{http_code}' "$BASE/product_form.php?id=4")" "200"

# =============================================================================
section "Security headers and misc"
HDR=$(curl -s -D - -o /dev/null "$BASE/")
has "T38 X-Content-Type-Options header set" "$HDR" "X-Content-Type-Options: nosniff"
has "T38b Clickjacking protection (X-Frame-Options)" "$HDR" "X-Frame-Options: DENY"
has "T38c Content-Security-Policy header set" "$HDR" "Content-Security-Policy"
eq  "T39 Registration form has a CSRF token field" "$(curl -s "$BASE/register.php" | grep -c 'name="csrf_token"')" "1"

sql "DELETE FROM users WHERE email='test@example.com'" >/dev/null
rm -rf "$TMP"
printf "\n========================================\n  %d passed, %d failed\n========================================\n" "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
