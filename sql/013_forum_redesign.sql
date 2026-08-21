
DROP TABLE IF EXISTS forum_question_votes;
DROP TABLE IF EXISTS forum_answer_votes;

ALTER TABLE forum_questions
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS forum_answer_helpful (
    user_id INT NOT NULL,
    answer_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, answer_id),
    CONSTRAINT fk_fah_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fah_answer FOREIGN KEY (answer_id) REFERENCES forum_answers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_answer_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    answer_id INT NOT NULL,
    user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(64) NOT NULL,
    size_bytes INT NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_faa_answer FOREIGN KEY (answer_id) REFERENCES forum_answers(id) ON DELETE CASCADE,
    CONSTRAINT fk_faa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
