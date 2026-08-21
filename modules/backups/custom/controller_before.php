<?php

if ($structure->permission !== '-') {
    include __DIR__.'/sections/automation.php';
}

if ($structure->permission === 'rw') {
    include __DIR__.'/sections/destinations.php';
}
