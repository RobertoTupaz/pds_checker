<?php

use App\Livewire\PdsUpload;
use Livewire\Livewire;

test('guests can view the pds checker landing page without logging in', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeLivewire(PdsUpload::class);
});

test('guests can render the pds checker component', function () {
    Livewire::test(PdsUpload::class)
        ->assertOk()
        ->assertSee('PDS Checker');
});
