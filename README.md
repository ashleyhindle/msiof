www.myserverisonfire.com
=====

Server dashboard that gives an easy to read overview of your infrastructure

![Image of MyServerIsOnFire](https://raw.githubusercontent.com/theahindle/msiof/master/web/images/screenshot.png)


## Requirements:


### Worker (ran on each server)
* PHP5 CLI
* PHP5 Curl

### API/Website 
* NGINX/Apache/Lighttpd
* PHP5
* Redis
* Composer

#### Installation:
```
cd /var/www/
git clone git@github.com:theahindle/msiof.git
cd msiof
composer install


Edit config file
Point domain to /var/www/msiof/web/
```

#### Nginx Config:
```
server {
    listen 80;
    server_name is.myserverisonfire.com;
    root /msiof/web/;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~* \.php$ {
        fastcgi_pass   unix:/var/run/php5-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }

}
```

#### Example Config - config/dev.json and config/prod.json
```
{
"msiof": {
		  "siteName": "MyServerIsOnFire.com",
					 "baseUrl": "https://myserverisonfire.com/",
					 "contactEmail": "hey@myserverisonfire.com",
					 "showEarlyAccessPage": true,
					 "registrationEnabled": true,
					 "paymentEnabled": false,
					 "issues": {
								"diskPercentage": 85,
								"memPercentage": 85,
								"notUpdatedMinutes": 5
					 },
					 "analytics": {
								"trackingId": "UA-46455757-1"
					 }
},
"msiof.stripe": {
		  "plans": {
					 "free": "freeplan",
					 "paid": "paidplan"
		  },
		  "freeServers": 3,
		  "pricePerServer": {
					 "USD": 300
		  },
		  "keys": {
					 "publishable": "pk_test_notreal",
					 "secret": "sk_test_notreal"
		  }
},
"debug": false,
"twig.path": "../views/",
"predis.parameters": "tcp://127.0.0.1:6379",
"predis.options": {
		  "prefix": "msiof:",
		  "profile": "3.0"
},
"db.options": {
		  "driver": "pdo_mysql",
		  "host": "localhost",
		  "dbname": "msiof",
		  "user": "msiof",
		  "password": "notarealpassword"
},
"swiftmailer.options": {
		  "host": "smtp.mandrillapp.com",
		  "port": "465",
		  "username": "ashley@smellynose.com",
		  "password": "notarealpassword",
		  "encryption": "ssl",
		  "auth_mode": null
},
"user.options": {
		  "templates": {
					 "layout": "layout.twig",
					 "register": "/account/register.twig",
					 "register-confirmation-sent": "/account/register-confirmation-sent.twig",
					 "login": "/account/login.twig",
					 "login-confirmation-needed": "/account/login-confirmation-needed.twig",
					 "forgot-password": "/account/forgot-password.twig",
					 "reset-password": "/account/reset-password.twig",
					 "view": "/account/view.twig",
					 "edit": "/account/edit.twig",
					 "list": "/account/list.twig"
		  },
		  "mailer": {
					 "enabled": true,
					 "fromEmail": {
								"address": "noreply@myserverisonfire.com",
								"name": null
					 }
		  },
		  "emailConfirmation": {
					 "required": true
		  }
}
}
```
