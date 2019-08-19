<?php
include 'src/import.php';

$moviesRepository = new \repository\movies();

$genres = $moviesRepository->getUniqueGenres()[0];

foreach ($genres as $genre) {
    var_dump('===========================================');
    var_dump($genre);
    var_dump('===========================================');
    var_dump($moviesRepository->getMoviesByGenre($genre));

}