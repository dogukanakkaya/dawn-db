<?php
namespace Codethereal\Database\Sqlite;

use SQLite3;

class Singleton extends SQLite3
{
    private static $instance;

    /**
     * Call this method to get singleton
     * @return Singleton
     */
    public static function instance()
    {
        # If no instance then make one
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Make constructor private, so nobody can call "new Class".
     */
    private function __construct() {
        $this->open('test.db');
    }

    /**
     * Make clone magic method private, so nobody can clone instance.
     */
    private function __clone() {}

    /**
     * Make sleep magic method private, so nobody can serialize instance.
     */
    private function __sleep() {}

    /**
     * Make wakeup magic method private, so nobody can unserialize instance.
     */
    private function __wakeup() {}
}