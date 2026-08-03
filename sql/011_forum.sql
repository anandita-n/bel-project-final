USE bel_pms;

CREATE TABLE IF NOT EXISTS forum_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    view_count INT NOT NULL DEFAULT 0,
    accepted_answer_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fa_question FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE forum_questions
    ADD CONSTRAINT fk_fq_accepted_answer FOREIGN KEY (accepted_answer_id) REFERENCES forum_answers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS forum_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_question_tags (
    question_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (question_id, tag_id),
    CONSTRAINT fk_fqt_question FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_fqt_tag FOREIGN KEY (tag_id) REFERENCES forum_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_question_votes (
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    value TINYINT NOT NULL,
    PRIMARY KEY (user_id, question_id),
    CONSTRAINT fk_fqv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fqv_question FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_answer_votes (
    user_id INT NOT NULL,
    answer_id INT NOT NULL,
    value TINYINT NOT NULL,
    PRIMARY KEY (user_id, answer_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_answer FOREIGN KEY (answer_id) REFERENCES forum_answers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
