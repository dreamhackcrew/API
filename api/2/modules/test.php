<?php 
class test {
    static function testar() {
        return db()->fetchAll("SELECT * FROM events");
	}
};
