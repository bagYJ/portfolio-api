<?php

use App\Models\MemberCard;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        ini_set('memory_limit','512M');
        foreach (User::with(['memberCard'])->cursor() as $user) {
            if ($user->memberCard->where('yn_main_card', 'Y')->count() <= 0 && empty($user->memberCard?->first()->no_card) === false) {
                MemberCard::where([
                    'no_user' => $user->no_user,
                    'no_card' => $user->memberCard->first()->no_card
                ])->update(['yn_main_card' => 'Y']);
            }
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
