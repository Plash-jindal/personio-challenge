BEGIN TRANSACTION;

CREATE TABLE `employees`
(
    `id`            INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    `name`          TEXT    NOT NULL,
    `supervisor_id` INTEGER DEFAULT NULL
);

COMMIT;
