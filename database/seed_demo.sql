-- Optional demo data. Import AFTER schema.sql.
-- Demo logins (DELETE these two accounts before sharing a real, public site):
--   admin@shelfwise.test / Admin@1234   (role: admin, can manage every product)
--   demo@shelfwise.test  / Demo@1234    (role: user, can manage only their own products)

SET NAMES utf8mb4;

INSERT INTO users (id, name, email, password_hash, role) VALUES
  (1, 'Shop Admin', 'admin@shelfwise.test', '$2y$10$On7vsvmgZkeFDxoRKKNQzu5FLB0ll7F52GbSgow3CfrMCueYMIqT.', 'admin'),
  (2, 'Demo Seller', 'demo@shelfwise.test', '$2y$10$Arm6kk8lMH7x/l7Brn8GAe3tAp1Ncex.Or2Xm9oqJL1L7i0QR6jBO', 'user');

INSERT INTO products (user_id, category_id, name, description, price, stock) VALUES
  (1, 1, 'Wireless Bluetooth Headphones', 'Over-ear headphones with 30 hours of battery life, soft ear cushions and a built-in microphone for calls.', 3499.00, 24),
  (1, 1, 'Portable Power Bank 20000mAh', 'Two USB-A ports and one USB-C port. Charges a phone about five times on a single charge.', 2150.00, 40),
  (1, 1, 'Mechanical Keyboard (Tenkeyless)', 'Compact keyboard with tactile blue switches and a detachable USB-C cable.', 4800.00, 3),
  (2, 1, 'Smart LED Desk Lamp', 'Adjustable colour temperature and brightness with a touch dimmer and USB charging port.', 1850.00, 15),
  (1, 2, 'Cotton Panjabi, Sky Blue', 'Breathable pure cotton panjabi with hand-stitched collar. Available in sizes M to XXL.', 1650.00, 32),
  (2, 2, 'Leather Bifold Wallet', 'Genuine leather wallet with six card slots and a coin pocket.', 950.00, 60),
  (2, 2, 'Canvas Backpack', 'Water-resistant canvas backpack with a padded 15 inch laptop sleeve.', 2300.00, 0),
  (1, 3, 'Non-stick Frying Pan 28cm', 'Induction-compatible pan with a scratch-resistant coating and a heat-proof handle.', 1450.00, 18),
  (2, 3, 'Stainless Steel Water Bottle 750ml', 'Double-wall vacuum insulated. Keeps drinks cold for 24 hours or hot for 12.', 890.00, 75),
  (1, 3, 'Electric Rice Cooker 1.8L', 'One-touch cooking with keep-warm mode and a removable inner pot.', 3200.00, 5),
  (1, 4, 'A5 Hardcover Notebook', 'Dotted pages, 192 sheets of 100gsm paper that resists ink bleed. Ribbon bookmark included.', 320.00, 120),
  (2, 4, 'Gel Pen Set (12 colours)', 'Smooth-writing 0.5mm gel pens in twelve colours, ideal for notes and journaling.', 280.00, 90),
  (2, 5, 'Yoga Mat 6mm', 'Non-slip textured surface with a carrying strap. Easy to wipe clean.', 1250.00, 22),
  (1, 5, 'Adjustable Dumbbell Pair 10kg', 'Two dumbbells with adjustable plates and secure collars.', 2750.00, 8),
  (2, 6, 'Herbal Face Wash 150ml', 'Gentle daily cleanser with neem and aloe vera. Suitable for all skin types.', 340.00, 85),
  (1, 6, 'Digital Thermometer', 'Fast, accurate reading in ten seconds with a beep alert and memory of the last result.', 420.00, 2),
  (2, 7, 'Wooden Building Blocks (100 pcs)', 'Colourful blocks in a cotton storage bag. Safe, smooth finish for ages 3 and up.', 1100.00, 14),
  (1, 7, 'Classic Chess Set', 'Folding wooden board with weighted pieces and felt bases.', 1600.00, 11),
  (1, 8, 'Sundarban Honey 500g', 'Raw, unprocessed honey collected from the Sundarbans. No added sugar.', 780.00, 45),
  (2, 8, 'Premium Basmati Rice 5kg', 'Long grain aromatic basmati rice, aged for extra fluffiness.', 950.00, 0),
  (2, 1, 'USB-C Fast Charger 65W', 'Compact GaN charger for laptops, tablets and phones.', 2400.00, 28),
  (1, 4, 'Sketchbook, 100 sheets', 'Heavyweight 160gsm paper suitable for pencil, charcoal and light washes.', 450.00, 34);
