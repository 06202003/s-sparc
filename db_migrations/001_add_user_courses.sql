-- Full DB bootstrap / migration for TASSTRANGE-GENAI
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS, ADD COLUMN IF NOT EXISTS)
-- Tested with MySQL 8.x syntax.

-- ====================================================================
-- 1) Core auth tables
-- ====================================================================

CREATE TABLE IF NOT EXISTS users (
    user_id        CHAR(36) NOT NULL,
    username       VARCHAR(191) NOT NULL,
    email          VARCHAR(191) NOT NULL,
    password_hash  VARCHAR(191) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gamification: poin total per minggu
CREATE TABLE IF NOT EXISTS user_points (
    user_id     CHAR(36) NOT NULL,
    total_points INT NOT NULL DEFAULT 0,
    updated_at  DATETIME DEFAULT NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_points_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- 2) Course & assessment
-- ====================================================================

CREATE TABLE IF NOT EXISTS courses (
    course_id   CHAR(36) NOT NULL,
    code        VARCHAR(100) NOT NULL,
    name        VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id),
    UNIQUE KEY uq_courses_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessments (
    assessment_id CHAR(36) NOT NULL,
    course_id     CHAR(36) NOT NULL,
    code          VARCHAR(100) NOT NULL,
    name          VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assessment_id),
    KEY idx_assessments_course (course_id),
    CONSTRAINT fk_assessments_course FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relation table between users and courses (IAM-style enrollment)
CREATE TABLE IF NOT EXISTS user_courses (
    id        CHAR(36) NOT NULL,
    user_id   CHAR(36) NOT NULL,
    course_id CHAR(36) NOT NULL,
    role      ENUM('student','instructor','admin') DEFAULT 'student',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_course (user_id, course_id),
    KEY idx_user_courses_user (user_id),
    KEY idx_user_courses_course (course_id),
    CONSTRAINT fk_user_courses_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_user_courses_course FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- 3) Chat history & tokens
-- ====================================================================

CREATE TABLE IF NOT EXISTS chat_history (
    id            CHAR(36) NOT NULL,
    user_id       CHAR(36) NOT NULL,
    session_id    VARCHAR(191) NOT NULL,
    assessment_id CHAR(191) DEFAULT NULL,
    role          ENUM('user','assistant','system') NOT NULL,
    content       MEDIUMTEXT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_user_session (user_id, session_id),
    KEY idx_chat_assessment (assessment_id),
    CONSTRAINT fk_chat_history_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Token usage log per aksi (session_tokens dipakai sebagai log)
CREATE TABLE IF NOT EXISTS session_tokens (
    id          CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    session_id  VARCHAR(191) NOT NULL,
    tokens_used INT NOT NULL DEFAULT 0,
    used_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- kolom lama yang mungkin sudah ada di DB lama
    total_tokens INT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_session_tokens_user (user_id),
    KEY idx_session_tokens_session (session_id),
    CONSTRAINT fk_session_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pastikan kolom yang dipakai kode terbaru ada.
-- Catatan: kolom tokens_used dan used_at sudah ada di sebagian DB,
-- jadi blok ALTER ini dikomentari agar import tidak gagal karena duplikat.
-- ALTER TABLE session_tokens
--     ADD COLUMN tokens_used INT NOT NULL DEFAULT 0,
--     ADD COLUMN used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ====================================================================
-- 4) GPT jobs & embeddings
-- ====================================================================

CREATE TABLE IF NOT EXISTS gpt_jobs (
    job_id        CHAR(36) NOT NULL,
    user_id       CHAR(36) NOT NULL,
    prompt        MEDIUMTEXT NOT NULL,
    status        ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
    code          MEDIUMTEXT NULL,
    error         TEXT NULL,
    similarity    FLOAT NULL,
    prompt_matched MEDIUMTEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_gpt_jobs_status (status),
    KEY idx_gpt_jobs_user (user_id),
    CONSTRAINT fk_gpt_jobs_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS code_embeddings (
    id        CHAR(36) NOT NULL,
    user_id   CHAR(36) NOT NULL,
    prompt    MEDIUMTEXT NOT NULL,
    code      MEDIUMTEXT NOT NULL,
    embedding LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_code_embeddings_user (user_id),
    KEY idx_code_embeddings_created (created_at),
    CONSTRAINT fk_code_embeddings_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- 5) Environmental impact & local carbon logs
-- ====================================================================

CREATE TABLE IF NOT EXISTS environmental_impact_logs (
    id          CHAR(36) NOT NULL,
    user_id     CHAR(36) NOT NULL,
    job_id      CHAR(36) NOT NULL,
    energy_wh   DOUBLE NOT NULL,
    energy_kwh  DOUBLE NOT NULL,
    carbon_kg   DOUBLE NOT NULL,
    water_ml    DOUBLE NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_eil_user (user_id),
    KEY idx_eil_job (job_id),
    CONSTRAINT fk_eil_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_eil_job FOREIGN KEY (job_id) REFERENCES gpt_jobs(job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS local_carbon_logs (
    id          CHAR(36) NOT NULL,
    server_name VARCHAR(191) NOT NULL,
    carbon_kg   DOUBLE NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lcl_server (server_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- 6) Helper indexes / safety
-- ====================================================================

-- Tambahan index. Jika index sudah ada dan muncul error di sini,
-- bagian ini dikomentari supaya script bisa di-import di semua DB.
-- ALTER TABLE code_embeddings
--     ADD INDEX idx_code_embeddings_user (user_id),
--     ADD INDEX idx_code_embeddings_created (created_at);

-- ALTER TABLE chat_history
--     ADD INDEX idx_chat_user_session (user_id, session_id),
--     ADD INDEX idx_chat_assessment (assessment_id);

