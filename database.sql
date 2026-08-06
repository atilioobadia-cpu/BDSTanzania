-- =====================================================================
-- BDS Tanzania — News Updates schema
-- Import this file in phpMyAdmin (or mysql CLI) on your cPanel database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS updates (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(200) NOT NULL,
    slug          VARCHAR(220) NOT NULL,
    excerpt       VARCHAR(500) DEFAULT NULL,
    content       MEDIUMTEXT DEFAULT NULL,
    image_path    VARCHAR(255) DEFAULT NULL,
    status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at  DATETIME NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_updates_slug (slug),
    KEY idx_updates_status_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: a sample published post so the Updates page is never empty.
-- INSERT INTO updates (title, slug, excerpt, content, image_path, status, published_at)
-- VALUES (
--     'Welcome to BDS Tanzania Updates',
--     'welcome-to-bds-tanzania-updates',
--     'BDS Tanzania is now publishing news, announcements and programme updates here.',
--     '<p>This is the first update. Watch this space for announcements, events and publications from BDS Tanzania.</p>',
--     'assets/images/logos/logo.webp',
--     'published',
--     NOW()
-- );
