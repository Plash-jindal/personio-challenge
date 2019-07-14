# personio-challenge

> Ever-changing Hierarchy API using [Slim Framework 3](https://www.slimframework.com/) and [SQLite 3](http://www.sqlite.org/).

## Usage

To install dependencies:

	composer install

To create the database:

	composer db

To run the application in development mode:

	composer start

## API

### Employees

- GET `/employees`
- POST `/employees` : _fields: `employees`