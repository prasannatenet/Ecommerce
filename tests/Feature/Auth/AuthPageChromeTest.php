<?php

/*
|--------------------------------------------------------------------------
| Auth page chrome
|--------------------------------------------------------------------------
|
| The auth screens (login / register) can opt out of the global frontend
| chrome by declaring @section('hide_topbar_navbar', true) and
| @section('hide_footer', true), which layouts/frontend reads with
| @sectionMissing().
|
| Those two sections are currently COMMENTED OUT in resources/views/auth/
| login.blade.php and register.blade.php, so the chrome is visible again and
| the two opt-out tests below are skipped on purpose. Delete the ->skip(...)
| calls (and restore the @section lines) to bring the opt-out back.
|
*/

it('hides the global chrome on the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('auth-page', false)
        ->assertDontSee('id="topBar"', false)
        ->assertDontSee('site-header', false)
        ->assertDontSee('<footer', false);
})->skip('login.blade.php no longer declares the hide_topbar_navbar / hide_footer sections.');

it('hides the global chrome on the register page', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('auth-page', false)
        ->assertDontSee('id="topBar"', false)
        ->assertDontSee('site-header', false)
        ->assertDontSee('<footer', false);
})->skip('register.blade.php no longer declares the hide_topbar_navbar / hide_footer sections.');

it('still renders the global topbar, navbar and footer on public pages', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="topBar"', false)
        ->assertSee('site-header', false)
        ->assertSee('<footer', false);
});
