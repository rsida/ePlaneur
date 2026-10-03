#!/bin/bash
# Runs once, when the database volume is created (dev only).
# Lets the application user create and use the test databases
# (`<database>_test`, plus `<database>_test<N>` when tests run in parallel).
set -e

mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}\_test%\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
