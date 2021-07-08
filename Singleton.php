<?php
namespace Codethereal\Database\Sqlite;

use SQLite3;

class Singleton extends SQLite3
{
    private static Singleton|null $instance = null;

    private static string $path = '';

    /**
     * Call this method to get singleton
     */
    public static function instance($path): Singleton
    {
        self::$path = $path;
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
        parent::__construct(self::$path);
    }
}