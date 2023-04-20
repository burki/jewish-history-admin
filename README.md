Legacy back-end <https://jewish-history-online.net>
===================================================

This is the code for the back-end of the site. It is an old code base
that has been tweaked for various research projects
(<https://docupedia.de>, eJournal Kritikon) but isn't recommended for any
new project due to its age.

You may use it in parts or adjust it to your own needs.
If you have any questions or find this code helpful, please contact us at
    <https://jewish-history-online.net/contact>

License
-------
    Code for the back-end of the Digital Source Edition
        Key Documents of German-Jewish History

        (C) 2018-2023 Daniel Burckhardt


    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

Third Party Code
----------------
This projects builds on numerous third-party projects under a variety of
Open Source Licenses. Please check `composer.json` for these dependencies.

Installation
------------

    git clone git@github.com:burki/jewish-history-admin.git

    cd jewish-history-admin
    composer install

    cp inc/local.inc.php-dist inc/local.inc.php
    # adjust settings as needed

### Create and populate the database

Create a proper database and create the table-structure

    mysqladmin -u root create jgo_admin
    # create user/password
    # then insert table-structure
    mysql -u jgo_admin -p jgo_admin < sql/tables.sql

    Insert an initial user with administrator privs into the empty table

Insert an initial user with administrator privs into the empty table

    INSERT INTO User (email, privs) VALUES ('my@example.com', 0x02 | 0x04);
