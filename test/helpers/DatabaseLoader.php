<?php

class DatabaseLoader
{
    private $db;
    public static $instances = [];

    public function __construct($db)
    {
        $this->db = $db;

        if (!isset(static::$instances[$db->protocol])) {
            static::$instances[$db->protocol] = 0;
        }

        if (0 == static::$instances[$db->protocol]++) {
            // drop and re-create the tables one time only
            $this->drop_tables();
            $this->exec_sql_script($db->protocol);
        }
    }

    public function reset_table_data()
    {
        foreach ($this->get_fixture_tables() as $table) {
            if ('oci' == $this->db->protocol && 'rm-bldg' == $table) {
                continue;
            }

            $this->db->query('DELETE FROM ' . $this->quote_name($table));
            $this->load_fixture_data($table);
        }

        $after_fixtures = $this->db->protocol . '-after-fixtures';
        try {
            $this->exec_sql_script($after_fixtures);
        } catch (Exception $e) {
            // pass
        }
    }

    public function drop_tables()
    {
        $tables = $this->db->tables();

        foreach ($this->get_fixture_tables() as $table) {
            if ('oci' == $this->db->protocol) {
                $table = strtoupper($table);

                if ('RM-BLDG' == $table) {
                    continue;
                }
            }

            if (in_array($table, $tables)) {
                $this->db->query('DROP TABLE ' . $this->quote_name($table));
            }

            if ('oci' == $this->db->protocol) {
                try {
                    $this->db->query("DROP SEQUENCE {$table}_seq");
                } catch (ActiveRecord\DatabaseException $e) {
                    // ignore
                }
            }
        }
    }

    public function exec_sql_script($file)
    {
        foreach (explode(';', $this->get_sql($file)) as $sql) {
            if ('' != trim($sql)) {
                $this->db->query($sql);
            }
        }
    }

    public function get_fixture_tables()
    {
        $tables = [];

        foreach (glob(__DIR__ . '/../fixtures/*.csv') as $file) {
            $info = pathinfo($file);
            $tables[] = $info['filename'];
        }

        return $tables;
    }

    public function get_sql($file)
    {
        $file = __DIR__ . "/../sql/$file.sql";

        if (!file_exists($file)) {
            throw new Exception("File not found: $file");
        }

        return file_get_contents($file);
    }

    public function load_fixture_data($table)
    {
        $fp = fopen(__DIR__ . "/../fixtures/$table.csv", 'r');
        $fields = fgetcsv($fp);

        if (!empty($fields)) {
            $markers = join(',', array_fill(0, count($fields), '?'));
            $table = $this->quote_name($table);

            foreach ($fields as &$name) {
                $name = $this->quote_name(trim($name));
            }

            $fields = join(',', $fields);

            while (($values = fgetcsv($fp))) {
                $this->db->query("INSERT INTO $table($fields) VALUES($markers)", $values);
            }
        }
        fclose($fp);
    }

    public function quote_name($name)
    {
        if ('oci' == $this->db->protocol) {
            $name = strtoupper($name);
        }

        return $this->db->quote_name($name);
    }
}
