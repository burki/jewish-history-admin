CREATE TABLE User (
  id int(11) NOT NULL auto_increment,
  status int(11) NOT NULL DEFAULT 0,
  status_flags  INT DEFAULT 0,
  changed timestamp NULL NULL,
  changed_by    INT NULL,                        #
  created datetime NULL,
  ip varchar(16) NULL,
  subscribed date NULL,
  unsubscribed date NULL,
  hold date NULL,
  email varchar(255) NULL,
  firstname varchar(255) NULL,
  lastname varchar(255) NULL,
  slug VARCHAR(255) NULL,
  sex enum('F','M') NULL,
  title varchar(50) NULL,
  position varchar(255) NULL,
  email_work varchar(255) NULL,
  institution varchar(255) NULL,
  address text NULL,
  place varchar(80) NULL,
  zip varchar(8) NULL,
  country varchar(2) NULL,
  phone varchar(30) NULL,
  fax varchar(30) NULL,
  url varchar(255) NULL,
  supervisor varchar(255) NULL,
  description text null,
  description_de text null,
  gnd VARCHAR(10) NULL,
  areas text,
  expectations text,
  knownthrough varchar(255) NULL,
  forum varchar(255) NULL,
  review enum('N','Y') DEFAULT 'N',
  review_areas text,
  review_suggest text,
  comment text,
  pwd char(40) NULL,
  recover varchar(32) NULL,
  recover_datetime datetime NULL,
  privs int(11) default '0',
  access datetime NULL,
  PRIMARY KEY  (id)
) ENGINE=MyISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#INSERT INTO User (email, privs) VALUES ('daniel.burckhardt@sur-gmbh.ch', 6);

CREATE TABLE Term (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed
  category      VARCHAR(20) NOT NULL,           #
  id_parent     INT NULL,                       # for hierarchical trees
  ord           INT NOT NULL DEFAULT 0,         # order of keyword with category/id_parent
  name          VARCHAR(255) NOT NULL,          #
  created       DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to User.id: who created the entry
  changed       DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) ENGINE=MyISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Message (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  flags         INT DEFAULT 0,                  #
  status        INT DEFAULT 0,                  # -1: removed, 0: proofread, 1: publish
  status_flags  INT DEFAULT 0,
  section       VARCHAR(255) NULL,              # to which section(s) it belongs
  type          INT NOT NULL,                   #
  parent_id     INT NULL,                       # articles in issue...
  ord           INT DEFAULT 0,                  # Ordnung innerhalb Kids mit gleichem Parent
  lang          VARCHAR(5) NULL,
  subject       VARCHAR(255) NULL,              # Title of the node
  body          LONGTEXT NULL,                  # the entry in plain txt, XML, binary,
  published     DATETIME NULL,                  #
  slug          VARCHAR(255) NULL,              # slug
  url           VARCHAR(255) NULL,              # Permanent-Link
  urn           VARCHAR(255) NULL,              # URL
  tags          VARCHAR(255) NULL,              #
  editor        INT NULL,                       #
  license       VARCHAR(50) NULL,

  # review fields
  referee       INT NULL,                       #
  translator    INT NULL,                       #

  modified     DATETIME NULL,                   #
  publisher_request DATETIME NULL,
  publisher_received DATETIME NULL,
  reviewer_request DATETIME NULL,
  reviewer_sent DATETIME NULL,
  reviewer_deadline DATETIME NULL,
  reviewer_received DATETIME NULL,
  referee_sent DATETIME NULL,
  referee_deadline DATETIME NULL,
  publisher_vouchercopy DATETIME NULL,
  imprimatur_sent DATETIME NULL,
  comment_imprimatur TEXT NULL,
  comment_review  TEXT NULL,
  comment_markup  TEXT NULL,
  comment_bibliography  TEXT NULL,
  comment_translation  TEXT NULL,
  comment_translation_markup  TEXT NULL,

  # common fields
  comment       TEXT NULL,                      # internal comment
  changed       TIMESTAMP NULL,                 # last changed
  changed_by    INT NULL,                       # ref to User.id: who created the entry
  created       TIMESTAMP,                      # when it was created
  created_by    INT NULL                        # ref to User.id: who created the entry
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE INDEX MessageTypePublish ON Message (type, published);

CREATE TABLE MessageUser (
  message_id    INT NOT NULL REFERENCES Message.id,  # to which message it belongs
  user_id       INT NOT NULL REFERENCES User.id,
  ord           INT NOT NULL DEFAULT 0          # Order for multiple User for one Message
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE UNIQUE INDEX idxMessageUser ON MessageUser(message_id, user_id);

CREATE TABLE Publisher (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  name          VARCHAR(255),                   # Name of the publisher
  domicile      VARCHAR(255) NULL,              # Domicile
  isbn          VARCHAR(255) NULL,              # ISBN prefix(es)

  # general address
  address text NULL,
  place varchar(80) NULL,
  zip varchar(8) NULL,
  country varchar(2) NULL,
  phone varchar(30) NULL,
  fax varchar(30) NULL,
  email VARCHAR(255) NULL,                      # contact-email
  url varchar(255) NULL,
  gnd VARCHAR(20) NULL,

  # contact person
  name_contact  VARCHAR(127) NULL,              # contact person
  email_contact VARCHAR(255) NULL,              # contact-email
  phone_contact VARCHAR(30) NULL,               # contact-direct line
  fax_contact   VARCHAR(30) NULL,               # fax

  # Common
  comments_internal TEXT NULL,                  # Interner Vermerk

  changed       TIMESTAMP NULL,                 # last changed
  changed_by    INT NULL,                       # ref to User.id: who created the entry
  created       TIMESTAMP,                      # when it was created
  created_by    INT NULL                        # ref to User.id: who created the entry
) ENGINE=MyISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Communication (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  flags         INT DEFAULT 0,                  #
  status        INT DEFAULT 0,                  # -1: removed, 0: proofread, 1: publish
  type          INT NOT NULL,                   #
  parent_id     INT NULL,                       # if we ever want to do threads
  ord           INT DEFAULT 0,                  # Ordnung innerhalb Kids mit gleichem Parent
  from_id       INT NOT NULL REFERENCES User.id,# User that sent
  from_email    VARCHAR(255) NULL,              # Override User.email
  to_id         INT NULL,                       # User/Publisher it was sent to
  to_email      VARCHAR(255) NULL,              # Override User/Publisher.email
  message_id    INT NULL,                       # Which message this Communication relates to
  subject       VARCHAR(255) NULL,              # Title of the node
  body          LONGTEXT NULL,                  # the entry in plain txt, XML, binary,
  sent          DATETIME NULL,                  #

  # common fields
  comment       TEXT NULL,                      # internal comment
  changed       TIMESTAMP NULL,                 # last changed
  changed_by    INT NULL,                       # ref to User.id: who created the entry
  created       TIMESTAMP,                      # when it was created
  created_by    INT NULL                        # ref to User.id: who created the entry
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Publication (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  type          INT NOT NULL,                   #
  flags         INT DEFAULT 0,                  #
  status        INT DEFAULT 0,                  # -1: removed, 0: proofread, 1: publish
  status_flags  INT DEFAULT 0,
  isbn          VARCHAR(13) NULL,               #
  title         VARCHAR(255) NOT NULL,          #
  subtitle      VARCHAR(511) NULL,
  author        VARCHAR(255) NULL,              #
  editor        VARCHAR(255) NULL,              #
  binding       VARCHAR(50) NULL,               # Hardcover, paperback
  pages         VARCHAR(50) NULL,               #
  series        VARCHAR(255) NULL,              #
  publication_date DATE NULL,                   # year(-month(-day))
  publisher_id  INT NULL REFERENCES Publisher.id, #
  publisher     VARCHAR(127) NULL,              # TODO: should we normalize
  archive_location VARCHAR(511) NULL,           # for manuscripts
  place         VARCHAR(127) NULL,              #
  listprice     VARCHAR(50) NULL,               #
  url           VARCHAR(511) NULL,              # link to TOC and similar things
  license       VARCHAR(50) NULL,
  attribution   TEXT NULL,

  lang          VARCHAR(5) NULL,
  translator    INT NULL,                       #
  translator_de INT NULL,                       #
  status_translation  INT NULL,                 #
  place_identifier VARCHAR(255) NULL,           #
  place_geo VARCHAR(255) NULL,                  #
  indexingdate  DATE NULL,
  displaydate VARCHAR(127) NULL,                #

  comment_digitization  TEXT NULL,
  comment_markup  TEXT NULL,
  comment_bibliography  TEXT NULL,
  comment_translation  TEXT NULL,
  comment_translation_markup  TEXT NULL,

  # Common
  comment       TEXT NULL,                      # internal comment
  changed       TIMESTAMP NULL,                 # last changed
  changed_by    INT NULL,                       # ref to User.id: who created the entry
  created       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, # when it was created
  created_by    INT NULL                        # ref to User.id: who created the entry
) ENGINE=MyISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE MessagePublication (
  message_id     INT NOT NULL REFERENCES Message.id,      # to which Message it belongs
  publication_id INT NOT NULL REFERENCES Publication.id,
  ord            INT NOT NULL DEFAULT 0                   # Order for multiple Publications for one Item
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE UNIQUE INDEX idxMessagePublication ON MessagePublication(message_id, publication_id);

CREATE TABLE Media (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  flags         INT DEFAULT 0,                  #
  item_id       INT NULL,                       # to which item it belongs
  type          INT DEFAULT 0,                  # to what $TYPE_ ({ITEM|EXBHIBITION}) it belongs
  name          VARCHAR(20) NOT NULL,           # the images have a name
  mimetype      VARCHAR(80) NOT NULL,           # e.g image/gif, image/jpeg
  width         INT NOT NULL,                   #
  height        INT NOT NULL,                   #
  ord           INT DEFAULT 0,                  # Ordnung innerhalb Kids mit gleichem Parent
  changed       TIMESTAMP NULL,                 # last changed
  created       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, # when it was created
  caption       VARCHAR(255),                   # img-caption
  descr         TEXT NULL,                      # further stuff
  copyright     VARCHAR(255),                   # copyright
  original_name VARCHAR(255) NULL,              # the original file name
  additional    TEXT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE Index MediaItemName ON Media(item_id, name);

CREATE TABLE MediaEntity (
  media_id     INT NULL,                        # to which Media it belongs
  uri           VARCHAR(255) NOT NULL,          # link to Entity
  type          INT DEFAULT 0,                  # to what $TYPE_ ({PERSON|PLACE}) it belongs
  num          INT NOT NULL DEFAULT 1           # how often it appears
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE UNIQUE INDEX idxMediaEntityUri ON MediaEntity (media_id, uri(127));

CREATE TABLE Person (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  lastname      VARCHAR(255),                   # Name of the Person
  firstname     VARCHAR(255) NULL,              # Firstname(s)

  title         VARCHAR(255) NULL,
  sex           ENUM('M', 'F') NULL,

  name_variant  TEXT NULL,

  birthdate     DATE NULL,
  deathdate     DATE NULL,
  birthdeath_note TEXT NULL,

  birthplace    VARCHAR(255) NULL,
  deathplace    VARCHAR(255) NULL,
  actionplace   VARCHAR(255) NULL,
  exile         VARCHAR(255) NULL,

  study         VARCHAR(255) NULL,
  profession    VARCHAR(255) NULL,
  occupation    VARCHAR(255) NULL,

  country       CHAR(2) NULL,                   #
  cv            TEXT NULL,                      #

  url           VARCHAR(255) NULL,

  gnd           VARCHAR(10) NULL,               #
  viaf          VARCHAR(16) NULL,               #
  lc_naf        VARCHAR(16) NULL,               #

  literature    TEXT NULL,                      #
  archive       TEXT NULL,                      #
  estate        TEXT NULL,                      #
  pictures      TEXT NULL,                      #
  entityfacts   TEXT NULL,

  # Common
  comment_internal TEXT NULL,                   # Interner Vermerk

  created       DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to User.id: who created the entry
  changed       DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#CREATE FULLTEXT INDEX idxPersonFulltext ON Person (firstname, lastname);

CREATE TABLE Place (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed
  type          VARCHAR(50) NOT NULL,

  name          VARCHAR(255),
  name_variant  TEXT NULL,

  country_code  CHAR(2),
  parent_path   VARCHAR(1023),
  latitude      DOUBLE,
  longitude     DOUBLE,
  tgn           INT NULL,
  tgn_parent    INT NULL,
  gnd           VARCHAR(255) NULL,
  geonames_id   INT NULL,
  geonames_parent_adm1 INT NULL,
  geonames_parent_adm2 INT NULL,
  geonames_parent_adm3 INT NULL,

  # Common
  comment_internal TEXT NULL,                   # Interner Vermerk

  created       DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to User.id: who created the entry
  changed       DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Zotero (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id

  zoteroKey     VARCHAR(13) NOT NULL,           #
  zoteroModified DATETIME NOT NULL,
  zoteroVersion INT NOT NULL DEFAULT 0,
  zoteroData    LONGTEXT NOT NULL,

  corresp       VARCHAR(127),

  itemType      VARCHAR(127) NOT NULL,          #
  flags         INT DEFAULT 0,                  #
  status        INT DEFAULT 0,                  # -1: removed, 0: proofread, 1: publish
  status_flags  INT DEFAULT 0,
  title         VARCHAR(1023) NOT NULL,          #
  subtitle      VARCHAR(511) NULL,
  author        VARCHAR(255) NULL,              #
  editor        VARCHAR(255) NULL,              #
  series        VARCHAR(255) NULL,              #
  series_number  VARCHAR(50) NULL,               #
  volume        VARCHAR(50) NULL,
  number_of_volumes VARCHAR(50) NULL,
  edition       VARCHAR(50) NULL,               #
  place         VARCHAR(127) NULL,              #
  publisher_id  INT NULL REFERENCES Publisher.id, #
  publisher     VARCHAR(127) NULL,              # TODO: should we normalize
  publication_date VARCHAR(50) NULL,            # year(-month(-day))
  num_pages     VARCHAR(50) NULL,               #
  lang          VARCHAR(5) NULL,
  isbn          VARCHAR(13) NULL,               #
  url           VARCHAR(511) NULL,              # link to TOC and similar things
  archive       VARCHAR(511) NULL,           # for manuscripts
  archive_location VARCHAR(511) NULL,           # for manuscripts
  extra         VARCHAR(511) NULL,
  listprice     VARCHAR(50) NULL,               #

  # Common
  comment       TEXT NULL,                      # internal comment
  changed       TIMESTAMP NULL,                 # last changed
  changed_by    INT NULL,                       # ref to User.id: who created the entry
  created       TIMESTAMP,                      # when it was created
  created_by    INT NULL                        # ref to User.id: who created the entry
) ENGINE=MyISAM CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
