www.myserverisonfire.com
=====

Server dashboard that gives an easy to read overview of your infrastructure

##Screenshot:
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

Point domain to /var/www/msiof/web/
```
