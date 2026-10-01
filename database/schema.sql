CREATE DATABASE IF NOT EXISTS khelmandu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE khelmandu;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('team', 'player', 'admin') NOT NULL DEFAULT 'team',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS player_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    home_city VARCHAR(100) NOT NULL,
    preferred_position VARCHAR(60) NOT NULL DEFAULT 'Flexible',
    skill_level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'intermediate',
    bio VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_player_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_players_city_skill (home_city, skill_level)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    home_city VARCHAR(100) NOT NULL,
    skill_level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'intermediate',
    player_count TINYINT UNSIGNED NOT NULL DEFAULT 5,
    bio VARCHAR(500) NOT NULL DEFAULT '',
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verification_file VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_teams_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teams_city_skill (home_city, skill_level)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS team_join_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    player_user_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_join_requests_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_join_requests_player FOREIGN KEY (player_user_id) REFERENCES player_profiles(user_id) ON DELETE CASCADE,
    INDEX idx_join_requests_team (team_id, status, created_at),
    INDEX idx_join_requests_player (player_user_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    player_user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    join_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_player FOREIGN KEY (player_user_id) REFERENCES player_profiles(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_request FOREIGN KEY (join_request_id) REFERENCES team_join_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS venues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    area VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    hourly_rate DECIMAL(10,2) UNSIGNED NOT NULL,
    surface VARCHAR(80) NOT NULL DEFAULT 'Indoor turf',
    facilities VARCHAR(500) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_venue_area_name (area, name),
    INDEX idx_venues_area_active (area, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS match_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_team_id BIGINT UNSIGNED NOT NULL,
    to_team_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    proposed_at DATETIME NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_requests_from_team FOREIGN KEY (from_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_requests_to_team FOREIGN KEY (to_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_requests_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    INDEX idx_requests_inbox (to_team_id, status, created_at),
    INDEX idx_requests_outbox (from_team_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL UNIQUE,
    home_team_id BIGINT UNSIGNED NOT NULL,
    away_team_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    kickoff_at DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matches_request FOREIGN KEY (request_id) REFERENCES match_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_home_team FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_away_team FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL,
    INDEX idx_matches_schedule (kickoff_at, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS venue_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    total_price DECIMAL(10,2) UNSIGNED NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    INDEX idx_booking_availability (venue_id, status, starts_at, ends_at),
    INDEX idx_booking_team (team_id, starts_at)
) ENGINE=InnoDB;

INSERT INTO venues (name, area, address, phone, hourly_rate, surface, facilities) VALUES
('Kick Off Futsal', 'Chabahil', 'Chabahil, Kathmandu', '01-4478123', 1800.00, 'Indoor turf', 'Changing rooms, parking, cafe'),
('Goal Park Arena', 'Baneshwor', 'New Baneshwor, Kathmandu', '01-4789012', 2000.00, 'Indoor turf', 'Changing rooms, water, parking'),
('The Futsal Hub', 'Lalitpur', 'Satdobato, Lalitpur', '01-5523410', 1600.00, 'Outdoor turf', 'Showers, equipment rental')
ON DUPLICATE KEY UPDATE name = VALUES(name);