# Laravel RABBIT PHP Listener

# TODO

| Done ?                   | Name                                  | Version       |
|:-------------------------|:--------------------------------------|:--------------|
| :hourglass_flowing_sand: | Write a Sender for this Listener.     | Pending       |
| :hourglass_flowing_sand: | Fix bug in `rabbit:terminate` command | Pending       |
| :white_large_square:     | Writing documentation.                | In the future |
| :white_large_square:     | Add middleware method.                | In the future |
| :white_large_square:     | Add Docs Auth Middleware.             | In the future |

# Install

```
composer require akbarali/rabbit-listener
```

After installing Rabbit Listener, publish its assets using the `rabbit:install` Artisan command:

```aiignore
php artisan rabbit:install
```

add `.env` rabbit configuration

```aiignore
RABBIT_HOST="host"
RABBIT_VHOST="vhost"
RABBIT_USER="user"
RABBIT_PASSWORD="pass"
```

# Configuration

After publishing Rabbit Listener's assets, its primary configuration file will be located at `config/rabbit.php`.
This configuration file allows you to configure the queue worker options for your application.
Each configuration option includes a description of its purpose, so be sure to thoroughly explore this file.

# Running Rabbit Listener

Once you have configured your supervisors and workers in your application's `config/rabbit.php` configuration file, you may start Rabbit Listener using the `rabbit:listener` Artisan command.
This single command will start all the configured worker processes for the current environment:

```aiignore
php artisan rabbit:listener
```

You may pause the Rabbit Listener process and instruct it to continue processing jobs using the `rabbit:pause` and `rabbit:continue` Artisan commands:

```
php artisan rabbit:pause
```

```aiignore
php artisan rabbit:continue
```

# Bonus

```aiignore
php artisan rabbit:info
```

# Deploying Rabbit Listener

When you're ready to deploy Rabbit Listener to your application's actual server, you should configure a process monitor to monitor the php artisan Rabbit Listener command and restart it if it exits unexpectedly.
Don't worry, we'll discuss how to install a process monitor below.
During your application's deployment process, you should instruct the Rabbit Listener process to terminate so that it will be restarted by your process monitor and receive your code changes:

```
php artisan rabbit:terminate
```

# Supervisor Configuration

Supervisor configuration files are typically stored within your server's `/etc/supervisor/conf.d` directory.
Within this directory, you may create any number of configuration files that instruct supervisor how your processes should be monitored.
For example, let's create a `rabbit.conf` file that starts and monitors a rabbit listener process:

```
[program:rabbit_listener]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan rabbit:channel:queue platform_queue_%(process_num)02d
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=10
user=forge
redirect_stderr=true
stdout_logfile=/var/www/supervisor/rabbit_listener_queue.log
```