<?php

use App\Enums\MemberType;
use App\Models\Member;
use App\Utils\Code;
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
        $admin = file_exists(Code::conf('admin_member_path')) ? file(Code::conf('admin_member_path')) : [];
        $ids = [];
        foreach ($admin as $id) {
            $ids[] = trim($id);
        }

        if (count($ids)) {
            Member::whereIn('id_user', $ids)->update([
                'cd_mem_type' => MemberType::MASTER_STAFF->value
            ]);
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
