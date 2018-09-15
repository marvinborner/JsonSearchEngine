# Introduction
This crawler gets all important information and all links of a website and writes the links to a queue.
After it has finished the information gathering, it will go on by using the first url of the queue and it will start again.

# Using the crawler
1. Create a mysql database: `mysql -u username -p` and `CREATE DATABASE database_name;`
2. Import the `database.sql` file into your database with `mysql -u username -p database_name < database.sql`
3. Edit `mysql_conf.inc` according to your databases credentials
4. Run `php crawler.php http://dmoztools.net/`
5. For future runs, just execute `php crawler.php` and it will automatically start with the first url of the queue
6. Finished!
 