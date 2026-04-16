CREATE TABLE IF NOT EXISTS runs (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    week_start           TEXT NOT NULL,
    week_end             TEXT NOT NULL,
    hcp_cache_dir        TEXT NOT NULL,
    hcp_cache_sha256     TEXT,
    price_sheet_path     TEXT NOT NULL,
    price_sheet_sha256   TEXT NOT NULL,
    created_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status               TEXT NOT NULL CHECK (status IN ('in_progress', 'final', 'superseded')),
    notes                TEXT
);

CREATE INDEX IF NOT EXISTS idx_runs_week_start ON runs(week_start);
CREATE INDEX IF NOT EXISTS idx_runs_status ON runs(status);

CREATE TABLE IF NOT EXISTS jobs (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id               INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    hcp_id               TEXT NOT NULL,
    hcp_job_number       TEXT NOT NULL,
    job_date             TEXT NOT NULL,
    customer_display     TEXT,
    description          TEXT,
    line_items_text      TEXT,
    notes_text           TEXT,
    amount               REAL NOT NULL,
    tip                  REAL NOT NULL DEFAULT 0,
    subtotal             REAL NOT NULL DEFAULT 0,
    labor                REAL NOT NULL DEFAULT 0,
    materials_charged    REAL NOT NULL DEFAULT 0,
    cc_fee               REAL NOT NULL DEFAULT 0,
    discount             REAL NOT NULL DEFAULT 0,
    raw_techs            TEXT,
    owner_tech           TEXT,
    skip_reason          TEXT,
    notes                TEXT,
    UNIQUE (run_id, hcp_id)
);

CREATE INDEX IF NOT EXISTS idx_jobs_run ON jobs(run_id);
CREATE INDEX IF NOT EXISTS idx_jobs_owner ON jobs(owner_tech);

CREATE TABLE IF NOT EXISTS job_parts (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id               INTEGER NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    part_name            TEXT NOT NULL,
    quantity             INTEGER NOT NULL,
    unit_price           REAL NOT NULL,
    total                REAL NOT NULL,
    source               TEXT NOT NULL DEFAULT 'manual'
);

CREATE INDEX IF NOT EXISTS idx_job_parts_job ON job_parts(job_id);

CREATE TABLE IF NOT EXISTS commissions (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id               INTEGER NOT NULL REFERENCES jobs(id) ON DELETE CASCADE,
    tech_name            TEXT NOT NULL,
    kind                 TEXT NOT NULL CHECK (kind IN ('primary', 'override')),
    basis                REAL NOT NULL,
    commission_pct       REAL NOT NULL,
    commission_amt       REAL NOT NULL,
    bonus_amt            REAL NOT NULL,
    override_amt         REAL NOT NULL,
    tip_amt              REAL NOT NULL,
    total                REAL NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_commissions_job ON commissions(job_id);
CREATE INDEX IF NOT EXISTS idx_commissions_tech ON commissions(tech_name);
