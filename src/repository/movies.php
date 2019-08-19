<?php

namespace repository;

use Db;

/**
 * Class movies
 *
 */
class movies extends \Base
{
    protected $table = 'movies';

    public function getUniqueGenres()
    {
        $db = Db::getInstance();
        $db->prepare("SELECT DISTINCT(genre) FROM movies ORDER BY genre ASC");
        return $db->execGetResults();
    }


    /**
     * @param string $catergory
     * @return array
     */
    public function getMoviesByGenre($genre)
    {
        $db = Db::getInstance();
        $db->prepare("SELECT * FROM " . $this->table . " WHERE genre = '" . $genre . "' ORDER BY year DESC");
        return $db->execGetResults();
    }
}