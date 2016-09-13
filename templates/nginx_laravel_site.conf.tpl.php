<?php
$conf = <<<EOF
server {
	listen 80;
	server_name {$dirName};
	server_name_in_redirect off;
# access_log {$nginxLogDir}{$dirName}.access.log;
# error_log {$nginxLogDir}{$dirName}.error.log error;
	index index.htm index.html index.php;
	autoindex on;

	client_max_body_size 1024M;
	client_body_buffer_size 4M;

	root {$docRoot}{$dirName};

	location / {
		allow 127.0.0.1;
		deny all;
		try_files \$uri \$uri/ /index.php?\$query_string;
	}

	location ~ (.+)\.php\$ {
		try_files \$uri =404;
		fastcgi_split_path_info ^(.+\.php)(.+)\$;
		fastcgi_pass	unix:{$phpFpmSockPath}; #путь до сокета php-fpm
		fastcgi_index index.php;
		include fastcgi_params;
		fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
	}

	location = /favicon.ico {
		log_not_found off;
		access_log off;
	}

	location = /robots.txt {
		allow all;
		log_not_found off;
		access_log off;
	}
	location ~* ^.+\.(jpg|jpeg|gif|png|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar)\$ {
		access_log off;
		expires max;
		error_page 404 = /404.html;#не забываем создать страницу
	}

	location ~ (/bitrix/modules|/upload|/support|/bitrix/php_interface) {
		deny all;
	}

	#все помнят это :)
		location ~ /.svn/ {
		deny all;
	}

	location ~ /\.ht {
		deny all;
	}
}
server {
	server_name www.{$dirName};
	return 301 \$scheme://{$dirName}\$request_uri;
}
EOF;
