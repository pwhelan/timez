Timez: A simple script driven timer app.
========================================

[![Stories in Ready](https://badge.waffle.io/pwhelan/timez.png?label=ready&title=Ready)](http://waffle.io/pwhelan/timez)

This is a dirt simple application to track time. It uses a PHP server to log
time states to a MongoDB server and uses a set of scripts to update the
state.


Installation
------------

Currently this only works on Linux. To get it running elsewhere should not
be an insurmountable task.

Requirements:

  * MongoDB
  * PHP 5.4
    * Composer
    * PHP Mongo Driver
  * xdotool
  * httpie

Install the requirements and clone the repository in some place, ie: ~/Code;

    $ mkdir -p ~/Code
    $ cd ~/Code
    $ git clone https://github.com/pwhelan/timez.git

After that execute the server (start from the same directory):

    $ cd timez # go into the repository
    $ cd server
    $ composer update
    $ cd public
    $ php -S 0.0.0.0:8000

Once that is done add a symlink to the timez command in your $PATH. I
usually add it to my ~/bin.

    $ cd ~/
    $ ln -s ~/Code/timez/bin/timez ~/bin/timez

Now all that's left is to execute the background job and start a task:

    $ timez periodic
    $ timez start "LOL-Cant-Use-Spaces-Yet" # TODO: fix this

The MongoDB database timez will now fill up with tasks and states and such,
ENJOY!
