weber
=====
Virtual hosts, nginx, php-fpm manager

Usage
=====
#### sample 1
Sets up bitrix site '/var/www/testsite.loc'. Creates database 'testsite' with collation 'cp1251_general_ci', user 'testsite'@localhost with granted full access identified by password '123'. Script also downloads helper-script for bitrix from https://1c-bitrix.ru/download/scripts/bitrixsetup.php.

`./weber setup-site testsite.loc bitrix --charset=cp1251`

#### sample 2
Sets up laravel site '/var/www/testsite2.loc'. Creates database 'testsite2' with collation 'utf8_general_ci', user 'testsite2'@localhost with granted full access identified by password '123'.

`./weber setup-site testsite2.loc laravel --charset=utf8`

#### sample 3
Deletes nginx virtual host files '/etc/nginx/sites-enabled/@testsite3.loc', '/etc/nginx/sites-available/testsite3.loc'. Removes site folder from document root ('/var/www/testsite3.loc')

`./weber delete-site testsite3.loc`
