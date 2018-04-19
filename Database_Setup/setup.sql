CREATE database reassured_app;

USE reassured_app;

CREATE TABLE users(
	id int(10) AUTO_INCREMENT,
	email VARCHAR(75) NOT NULL,
	password VARCHAR(300) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	firstname VARCHAR(75) NOT NULL,
	lastname VARCHAR(75) NOT NULL,
	team_id int(3) NOT NULL,
	location_id int(3) NOT NULL,
	last_known_lat VARCHAR(20) NULL,
	last_known_long VARCHAR(20) NULL,
        display_location CHAR(1) DEFAULT '0',
	PRIMARY KEY(id)
);

CREATE TABLE company_calendar(
	id int(10) AUTO_INCREMENT,
	event_name VARCHAR(75) NOT NULL,
	event_location VARCHAR(75) NOT NULL,
	event_organiser VARCHAR(75) NOT NULL,
	event_start TIMESTAMP NULL,
	event_information TEXT(9999) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
	primary key(id)
);

/*A list of company teams*/
CREATE TABLE teams(
	id int(3) AUTO_INCREMENT,
	team_name VARCHAR(40) NOT NULL,
	PRIMARY KEY(id)
);

/*Insert the teams into the database so they can be selected*/
INSERT INTO teams(`team_name`) VALUES ('IT'), ('HR'), ('Marketing'), ('Sales'), ('QA'), ('Processing'), ('Finance'), ('Remediation'), ('Facilities');

/*A list of company locations*/
CREATE TABLE locations(
	id int(3) AUTO_INCREMENT,
	location_name VARCHAR(30) NOT NULL,
	PRIMARY KEY(id)
);

/*Insert the locations into the database so that they can be selected*/
INSERT INTO locations(
  `location_name`
) VALUES (
  'Basingstoke'
), (
  'Manchester'
), (
  'Portsmouth'
);

/* This table is mainly used by the notifications API for FCM and messaging. It stores the tokens */
CREATE TABLE application_tokens(
	id int(10) AUTO_INCREMENT,
	user_id int(10) NOT NULL,
	application_token varchar(200) NOT NULL,
	PRIMARY KEY(id)
);

/*This is a table that contains scheduled meetings*/
CREATE TABLE scheduled_meetings(
	id int(10) AUTO_INCREMENT,
	organizer_id int(10) NOT NULL,
	location varchar(100) NOT NULL,
	title varchar(50) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	start_time TIMESTAMP NULL,
	duration INT(4),
	invited VARCHAR(2000) DEFAUlT "[]",
	attending VARCHAR(2000) DEFAULT "[]",
	declined VARCHAR(2000) DEFAULT "[]",
	PRIMARY KEY(id)
);

/*This is the table for all the company bulletin posts*/
CREATE TABLE bulletin_posts(
	id int(10) AUTO_INCREMENT,
	user_id INT(10) NOT NULL,
	post_body VARCHAR(1000) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(id)
);

/*This is the table for all the bulletin post comments*/
CREATE TABLE post_comments(
	id int(10) AUTO_INCREMENT,
	user_id INT(10) NOT NULL,
	post_id INT(10) NOT NULL,
	comment_body VARCHAR(1000) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(id)
);

/*This table will store emails*/
CREATE TABLE emails(
    id INT(10) AUTO_INCREMENT,
	user_id INT(10) NOT NULL,
	to_email VARCHAR(75) NOT NULL,
	subject VARCHAR(75) NOT NULL,
	email_body TEXT(9999) NOT NULL,
	created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	sent CHAR(1) DEFAULT '0',
    PRIMARY KEY(id)
);

/*This table will store the JOAN meeting room token*/
CREATE TABLE joan_token(
  id INT(1) AUTO_INCREMENT,
  token VARCHAR(30) NOT NULL,
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id)
);

INSERT INTO joan_token(
  `id`,
  `token`
) VALUES (
  '1',
  'HQGFE5PTh8bfusNiKeXzHRgnp1SBIL'
);

ALTER TABLE users
ADD COLUMN activated CHAR(1) DEFAULT '0';

ALTER TABLE users
ADD COLUMN activation_code VARCHAR(10) NOT NULL;

/*This table is used to approve pending actions*/
CREATE TABLE pending_actions(
  `id` INT(10) AUTO_INCREMENT,
  `action` VARCHAR(1000) NOT NULL,
  `passkey` VARCHAR(10) NOT NULL,
  `completed` CHAR(1) DEFAULT '0',
  `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `affects_user_id` INT(10) NOT NULL,
  PRIMARY KEY(id)
);

/*This script will create a table to store user to user messaging*/
CREATE TABLE user_messages(
  id INT(10) AUTO_INCREMENT,
  from_user_id INT(10) NOT NULL,
  to_user_id INT(10) NOT NULL,
  sent_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  message_body TEXT(9999) NOT NULL,
  PRIMARY KEY(id)
);
