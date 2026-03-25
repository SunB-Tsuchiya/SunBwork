<?php

use App\Models\User;

test('users can leave teams', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $user->currentTeam->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->actingAs($otherUser);

    $this->delete('/teams/'.$user->currentTeam->id.'/members/'.$otherUser->id);

    expect($user->currentTeam->fresh()->users)->toHaveCount(0);
});

test('team owners cant leave their own team', function () {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());

    // The withPersonalTeam factory creates the team but does not add the user to the
    // team_user pivot table. RemoveTeamMember::ensureUserDoesNotOwnTeam checks the
    // pivot for role='owner', so we must attach the user with that role explicitly.
    $user->currentTeam->users()->syncWithoutDetaching([
        $user->id => ['role' => 'owner'],
    ]);

    $response = $this->delete('/teams/'.$user->currentTeam->id.'/members/'.$user->id);

    $response->assertSessionHasErrorsIn('removeTeamMember', ['team']);

    expect($user->currentTeam->fresh())->not->toBeNull();
});
