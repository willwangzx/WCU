CREATE TABLE IF NOT EXISTS applications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NOT NULL,
  birth_month TEXT NOT NULL,
  birth_day INTEGER NOT NULL,
  birth_year INTEGER NOT NULL,
  gender TEXT NOT NULL,
  citizenship TEXT NOT NULL,
  entry_term TEXT NOT NULL,
  program TEXT NOT NULL,
  school_name TEXT NOT NULL,
  personal_statement TEXT NOT NULL,
  portfolio_url TEXT NOT NULL,
  additional_notes TEXT DEFAULT NULL,
  ip_address TEXT DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  origin_url TEXT DEFAULT NULL,
  created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_applications_email ON applications (email);
CREATE INDEX IF NOT EXISTS idx_applications_entry_term ON applications (entry_term);
CREATE INDEX IF NOT EXISTS idx_applications_program ON applications (program);
CREATE INDEX IF NOT EXISTS idx_applications_created_at ON applications (created_at);
