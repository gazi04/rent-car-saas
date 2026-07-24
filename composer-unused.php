<?php

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static function (Configuration $config): Configuration {
    return $config
        ->addNamedFilter(NamedFilter::fromString('laravel/chisel'))
        ->addNamedFilter(NamedFilter::fromString('livewire/blaze'))
        ->addNamedFilter(NamedFilter::fromString('livewire/flux'))
        ->addNamedFilter(NamedFilter::fromString('resend/resend-laravel'));
};
