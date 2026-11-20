-- N Magazine Database Schema

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `student_id` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student') NOT NULL DEFAULT 'student',
  `avatar` VARCHAR(50) DEFAULT 'ironman',
  `bio` TEXT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `faculty` VARCHAR(100) DEFAULT NULL,
  `social_facebook` VARCHAR(255) DEFAULT NULL,
  `social_twitter` VARCHAR(255) DEFAULT NULL,
  `social_linkedin` VARCHAR(255) DEFAULT NULL,
  `social_instagram` VARCHAR(255) DEFAULT NULL,
  `is_reported` TINYINT(1) DEFAULT 0,
  `report_reason` TEXT DEFAULT NULL,
  `banned_until` DATETIME DEFAULT NULL,
  `ban_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: articles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `category_id` INT NOT NULL,
  `author_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('approved', 'restricted', 'rejected', 'pending') NOT NULL DEFAULT 'approved',
  `views` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: comments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: bookmarks
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_article_unique` (`user_id`, `article_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Sample Data Insertion
-- Default Passwords:
-- Admin: admin123 (hashed with password_hash)
-- Student: student123 (hashed with password_hash)
-- Default users
INSERT INTO `users` (`id`, `name`, `student_id`, `email`, `password`, `role`, `faculty`, `bio`, `phone`, `social_facebook`, `social_twitter`, `social_linkedin`, `social_instagram`) VALUES
(1, 'N Magazine Admin', 'NSBM/ADMIN/01', 'admin@nsbm.ac.lk', '$2y$10$ZH0V2tvb5GZAgzKl4uW2Juyze0O6XkH6VSGjb2mjNIS29Uj9KdM6y', 'admin', 'Faculty of Computing', 'Chief Editor and Media Administrator at NSBM Green University NewsDesk.', '+94 11 544 5000', 'https://facebook.com', 'https://twitter.com', 'https://linkedin.com', 'https://instagram.com'),
(2, 'Kasun Perera', 'NSBM/SOC/2024/1042', 'student@nsbm.ac.lk', '$2y$10$66pZ0ILerrEgLjRD8zyjkO0/aUv1Mlk58JreodyId//1dCn.M9Yxi', 'student', 'Faculty of Computing', 'Software engineering undergraduate, hackathon enthusiast, and tech writer for N Magazine.', '+94 77 123 4567', 'https://facebook.com', 'https://twitter.com', 'https://linkedin.com', 'https://instagram.com'),
(3, 'Amaya Silva', 'NSBM/FOB/2024/0821', 'amaya@nsbm.ac.lk', '$2y$10$66pZ0ILerrEgLjRD8zyjkO0/aUv1Mlk58JreodyId//1dCn.M9Yxi', 'student', 'Faculty of Business', 'Undergraduate in Business Management, photography club member, and campus events blogger.', '+94 71 987 6543', 'https://facebook.com', 'https://twitter.com', 'https://linkedin.com', 'https://instagram.com')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Default categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Academic', 'academic', 'Lectures, exam schedules, course updates, and faculty notices.'),
(2, 'Events', 'events', 'University workshops, hackathons, guest sessions, and conferences.'),
(3, 'Clubs', 'clubs', 'Student clubs, societies, exhibitions, and campus communities.'),
(4, 'Uni Life', 'uni-life', 'Campus lifestyle, student culture, recreation, and living.'),
(5, 'Sports', 'sports', 'Inter-university sports leagues, cricket, badminton, and swimming meets.'),
(6, 'General', 'general', 'Campus life, student club activities, library hours, and transport info.')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `slug`=VALUES(`slug`), `description`=VALUES(`description`);

-- Sample articles
INSERT INTO `articles` (`id`, `title`, `category_id`, `author_id`, `content`, `image_url`, `status`, `views`) VALUES
(1, 'NSBM Annual Convocation 2026 Announced', 1, 1, 
'NSBM Green University is proud to announce the Annual Convocation 2026. The ceremonial event will take place over three consecutive days at the university auditorium, honoring undergraduate and postgraduate graduands from the Faculties of Computing, Business, and Engineering.\n\nGraduating students are requested to complete their gown fittings and guest invitation registrations through the student portal by next week. The chief guest for the inaugural session will be a renowned international academic leader.', 
'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&auto=format&fit=crop&q=80', 'approved', 145),

(2, 'HackNSBM 2026: 24-Hour National Hackathon Registrations Open', 2, 2, 
'The IEEE Student Branch and Computing Society of NSBM present HackNSBM 2026! Gather your teams of 3 to 4 members to build real-world software solutions under the themes of Green Technology, Smart Agriculture, and EdTech.\n\nAttractive cash prizes worth over LKR 500,000, industry mentorship, and direct internship opportunities await the finalists. Refreshments, high-speed campus Wi-Fi, and recreational spaces will be provided around the clock during the hackathon.', 
'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=800&auto=format&fit=crop&q=80', 'approved', 320),

(3, 'Inter-Faculty Cricket Championship Kicks Off at Green Oval', 5, 1, 
'The energetic sports calendar of NSBM kicked off today with the opening match of the Inter-Faculty Cricket Championship at the NSBM Green Oval. The Faculty of Computing clashed with the Faculty of Business in an electrifying 20-over contest.\n\nSupporters filled the pavilion with chants and papare bands. The tournament will continue throughout the weekend, culminating in the grand finale on Sunday afternoon under the floodlights.', 
'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80', 'approved', 210),

(4, 'Eco-Friendly Campus Initiative: New Electric Shuttle Schedule', 4, 1, 
'As part of our commitment to being South Asia\'s premier green university, NSBM has expanded its zero-emission electric shuttle bus fleet connecting the Pitipana bus stop and the main campus terminal.\n\nBuses will operate every 10 minutes during morning peak hours (7:30 AM to 9:30 AM) and evening hours (4:00 PM to 6:30 PM). Students can show their digital student ID card for free shuttle passes.', 
'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&auto=format&fit=crop&q=80', 'approved', 85),

(5, 'Student Club Proposal: NSBM Photography Society Exhibition', 3, 3, 
'The NSBM Photography Society plans to host an open photography exhibition titled "Echoes of Nature" in the student recreation lounge next month. We invite all student photographers to submit their best nature and architectural photographs capturing the beauty of our Pitipana green campus.\n\nSubmitted works will be judged by senior photographers and awarded at the closing ceremony.', 
'https://images.unsplash.com/photo-1452587925148-ce544e77e70d?w=800&auto=format&fit=crop&q=80', 'pending', 12)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Sample comments
INSERT INTO `comments` (`id`, `article_id`, `user_id`, `comment_text`) VALUES
(1, 1, 2, 'Congratulations to all graduating seniors!'),
(2, 2, 3, 'Can students from different faculties form a combined team?'),
(3, 2, 1, 'Yes, inter-faculty teams are welcomed and encouraged!')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Sample bookmark
INSERT INTO `bookmarks` (`user_id`, `article_id`) VALUES
(2, 2)
ON DUPLICATE KEY UPDATE `user_id`=`user_id`;

-- --------------------------------------------------------
-- Table: mindscape_thoughts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mindscape_thoughts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `author_name` VARCHAR(100) NOT NULL,
  `student_id` VARCHAR(50) DEFAULT NULL,
  `role` VARCHAR(50) DEFAULT 'student',
  `avatar_emoji` VARCHAR(30) DEFAULT '🌱',
  `avatar_bg` VARCHAR(60) DEFAULT 'linear-gradient(135deg, #16a34a, #059669)',
  `thought` TEXT NOT NULL,
  `gif_url` VARCHAR(500) DEFAULT NULL,
  `brick_color` VARCHAR(50) DEFAULT 'brick-emerald',
  `brick_size` VARCHAR(20) DEFAULT 'standard',
  `likes` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Sample Mindscape Thoughts
INSERT INTO `mindscape_thoughts` (`id`, `author_name`, `student_id`, `role`, `avatar_emoji`, `avatar_bg`, `thought`, `brick_color`, `brick_size`, `likes`, `created_at`) VALUES
(1, 'Kasun Perera', 'NSBM/SOC/2024/1042', 'student', '💻', 'linear-gradient(135deg, #0284c7, #0369a1)', 'HackNSBM 2026 is going to be wild! Anyone looking for a backend PHP/Node engineer for their team?', 'brick-emerald', 'wide', 14, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(2, 'Amaya Silva', 'NSBM/FOB/2024/0821', 'student', '📸', 'linear-gradient(135deg, #ec4899, #be185d)', 'The golden sunset over the Green Oval today looked like a painted masterpiece. Don\'t forget to look up from your screens!', 'brick-gold', 'standard', 28, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'N Magazine Admin', 'NSBM/ADMIN/01', 'admin', '🏛️', 'linear-gradient(135deg, #15803d, #16a34a)', 'Welcome to Mindscape! This is your campus wall. Drop your reflections, project ideas, exam motivation, or a simple hello.', 'brick-emerald', 'accent', 42, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(4, 'Dinuka Fernando', 'NSBM/FOE/2023/0411', 'student', '⚡', 'linear-gradient(135deg, #f59e0b, #d97706)', 'Final year robotics project completed testing today. Zero motor overheating! Coffee well spent.', 'brick-slate', 'standard', 9, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(5, 'Sithmi Jayawardena', 'NSBM/FOB/2025/1190', 'student', '🌿', 'linear-gradient(135deg, #10b981, #047857)', 'The new electric shuttles are super quiet and punctual. Big love to our eco-friendly green university town!', 'brick-mint', 'wide', 19, DATE_SUB(NOW(), INTERVAL 1 DAY))
ON DUPLICATE KEY UPDATE `id`=`id`;

