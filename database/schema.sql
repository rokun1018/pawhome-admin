-- =====================================================================
--  PawHome Admin: database for Supabase (PostgreSQL)
--  How to use: Supabase > SQL Editor > New query > paste this file > Run
--  Before running, change the admin name, email and password at the bottom.
-- =====================================================================

-- 1. SHELTERS: the 3 partner shelters. Edit them in Supabase's Table Editor.
CREATE TABLE shelters (
    id        SERIAL PRIMARY KEY,
    name      VARCHAR(120) NOT NULL,
    area      VARCHAR(120),
    capacity  INT NOT NULL DEFAULT 100          -- how many pets it can hold
);

-- 2. USERS: everyone who can sign in to the admin panel.
--    The role decides which pages they can open (see includes/config.php).
CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    full_name      VARCHAR(120) NOT NULL,
    email          VARCHAR(160) NOT NULL UNIQUE,
    phone          VARCHAR(40),
    password       VARCHAR(255) NOT NULL,       -- secure hash, never plain text
    role           VARCHAR(30)  NOT NULL DEFAULT 'volunteer'
                   CHECK (role IN ('super_admin','shelter_manager','veterinarian','volunteer_coordinator','volunteer')),
    status         VARCHAR(10)  NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
    avatar         TEXT,                        -- profile photo, stored as text so it survives restarts
    notify_new_application BOOLEAN NOT NULL DEFAULT TRUE,
    notify_boarding        BOOLEAN NOT NULL DEFAULT TRUE,
    notify_capacity        BOOLEAN NOT NULL DEFAULT TRUE,
    notify_weekly          BOOLEAN NOT NULL DEFAULT FALSE,
    reset_token    VARCHAR(64),                 -- for "forgot password"
    reset_expires  TIMESTAMPTZ,
    last_active    TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. SESSIONS: keeps people signed in, even when the free server restarts.
CREATE TABLE sessions (
    id          VARCHAR(128) PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE CASCADE,
    data        TEXT NOT NULL DEFAULT '',
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 4. PETS: every animal in PawHome care.
CREATE TABLE pets (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(80) NOT NULL,
    species      VARCHAR(20) NOT NULL CHECK (species IN ('dog','cat','rabbit','bird','other')),
    breed        VARCHAR(80),
    age_months   INT NOT NULL DEFAULT 0,
    gender       VARCHAR(10) NOT NULL CHECK (gender IN ('male','female')),
    size         VARCHAR(10) NOT NULL CHECK (size IN ('small','medium','large')),
    shelter_id   INT REFERENCES shelters(id) ON DELETE SET NULL,
    intake_date  DATE NOT NULL DEFAULT CURRENT_DATE,
    status       VARCHAR(20) NOT NULL DEFAULT 'available' CHECK (status IN ('available','reserved','adopted','medical')),
    photo        TEXT,
    description  TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. APPLICATIONS: adoption applications. The public "user" part of the site
--    will add rows here; the admin panel reviews them.
CREATE TABLE applications (
    id               SERIAL PRIMARY KEY,
    pet_id           INT REFERENCES pets(id) ON DELETE SET NULL,
    applicant_name   VARCHAR(120) NOT NULL,
    applicant_email  VARCHAR(160) NOT NULL,
    applicant_phone  VARCHAR(40),
    home_type        VARCHAR(60),
    quiz_score       INT NOT NULL DEFAULT 0,     -- percent, 0 to 100
    quiz_answers     JSONB,                      -- {"question id": chosen answer number}
    status           VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','review','approved','rejected')),
    admin_notes      TEXT,
    decided_at       TIMESTAMPTZ,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. BOARDING: short stays for owned pets.
CREATE TABLE boarding (
    id             SERIAL PRIMARY KEY,
    pet_name       VARCHAR(80) NOT NULL,
    species        VARCHAR(20) NOT NULL,
    breed          VARCHAR(80),
    owner_name     VARCHAR(120) NOT NULL,
    owner_phone    VARCHAR(40) NOT NULL,
    owner_email    VARCHAR(160),
    check_in       DATE NOT NULL,
    check_out      DATE NOT NULL CHECK (check_out >= check_in),
    kennel         VARCHAR(20),
    status         VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','checked_in','completed','cancelled')),
    special_notes  TEXT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 7. QUIZ QUESTIONS: the answer choices are kept in the same row as a list,
--    so no separate options table is needed.
CREATE TABLE quiz_questions (
    id                  SERIAL PRIMARY KEY,
    question_text       TEXT NOT NULL,
    category            VARCHAR(40) NOT NULL,
    pet_type            VARCHAR(20) NOT NULL DEFAULT 'all',
    difficulty          VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (difficulty IN ('easy','medium','hard')),
    status              VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('active','draft')),
    options             JSONB NOT NULL,          -- ["Answer A", "Answer B", ...]
    recommended_option  INT NOT NULL DEFAULT 0,  -- 0 = first answer
    created_by          INT REFERENCES users(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 8. SETTINGS: one row of organisation-wide settings.
CREATE TABLE settings (
    id              INT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    org_name        VARCHAR(120) NOT NULL DEFAULT 'PawHome Rescue Network',
    contact_email   VARCHAR(160) NOT NULL DEFAULT 'hello@pawhome.org',
    contact_phone   VARCHAR(40),
    quiz_pass_mark  INT NOT NULL DEFAULT 70,
    kennels         TEXT NOT NULL DEFAULT 'C-01,C-02,C-03,C-04,C-05,C-06,C-07,C-08,C-09,C-10,D-01,D-02,D-03,D-04,D-05,D-06,D-07,D-08,D-09,D-10,D-11,D-12',
    boarding_rate   NUMERIC(10,2) NOT NULL DEFAULT 0   -- price per night
);

-- 9. ACTIVITY LOG: sign-ins and changes. Feeds "Recent activity",
--    notifications and the staff activity report.
CREATE TABLE activity_log (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    user_name   VARCHAR(120),
    action      VARCHAR(60) NOT NULL,
    details     TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_pets_status        ON pets(status);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_boarding_dates     ON boarding(check_in, check_out);
CREATE INDEX idx_activity_created   ON activity_log(created_at DESC);

-- Block Supabase's public API from these tables. The PHP code connects as the
-- database owner, so it still has full access.
ALTER TABLE shelters       ENABLE ROW LEVEL SECURITY;
ALTER TABLE users          ENABLE ROW LEVEL SECURITY;
ALTER TABLE sessions       ENABLE ROW LEVEL SECURITY;
ALTER TABLE pets           ENABLE ROW LEVEL SECURITY;
ALTER TABLE applications   ENABLE ROW LEVEL SECURITY;
ALTER TABLE boarding       ENABLE ROW LEVEL SECURITY;
ALTER TABLE quiz_questions ENABLE ROW LEVEL SECURITY;
ALTER TABLE settings       ENABLE ROW LEVEL SECURITY;
ALTER TABLE activity_log   ENABLE ROW LEVEL SECURITY;

-- Starting data
INSERT INTO settings (id, contact_phone) VALUES (1, '+880 9612-345678');

INSERT INTO shelters (name, area, capacity) VALUES
    ('PawHome Center North',          'Bay Area',     120),
    ('Green Valley Rescue Sanctuary', 'Green Valley', 80),
    ('Southside Safe Haven',          'Southside',    60);

-- YOUR SUPER ADMIN ACCOUNT: change the name, email and password before running.
INSERT INTO users (full_name, email, phone, password, role, status)
VALUES (
    'Alex Johnston',
    'admin@pawhome.org',
    '+1 415 555 0101',
    extensions.crypt('ChangeThisPassword123', extensions.gen_salt('bf', 10)),
    'super_admin',
    'active'
);
