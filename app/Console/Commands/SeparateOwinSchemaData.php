<?php

namespace App\Console\Commands;

use App\Enums\AppType;
use App\Models\OrderList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\BufferedOutput;

class SeparateOwinSchemaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'separate-owin-schema-data {--schema=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '오윈 스키마 데이터를 다른 서비스 스키마로 분리합니다';

    protected $log = '';

    public function info($string, $verbosity = null)
    {
        parent::info($string);
        $this->log .= $string . PHP_EOL;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $schema = $this->option('schema');
        if (!$schema) {
            $this->error('분리하려는 스키마를 입력해주세요 ex)gtcs');
            return Command::FAILURE;
        }

        try {

            $this->info('=== 데이터 분리를 시작합니다 ===');

            /**
             * 공통 키 처리
             */
            $keys = ['cd_third_party', 'no_user', 'no_order', 'user_id'];

            $cd_third_parties = [
                'gtcs' => AppType::GTCS->value,
                'renault' => AppType::AVN->value,
                'tmap' => AppType::TMAP_AUTO->value
            ];

            $copied_tables = [];

            foreach ($keys as $key) {
                $this->info('== [ ' . $key . ' ] 키를 갖고 있는 테이블을 복사합니다 ==');
                $tables = $this->getBaseTablesHavingColumn($key, $schema);
                $this->info('< TABLES > ' . json_encode($tables));
                foreach ($tables as $table) {
                    $this->info('[ ' . $table . ' ] 테이블을 복사합니다');

                    if (array_search($table, $copied_tables) !== false) {
                        $this->info('[ ' . $table . ' ] 테이블은 이미 복사되었습니다');
                        continue;
                    }

                    if ($key === 'cd_third_party') {
                        $where = '= ' . $cd_third_parties[$schema];
                    } else if ($key === 'no_user' || $key === 'user_id') {
                        $where = 'in (select no_user from ' . $schema . '.member_detail)';
                    } else if ($key === 'no_order') {
                        $where = 'in (select no_order from ' . $schema . '.order_list)';
                    }


                    $sql = <<<SQL
INSERT INTO $schema.$table SELECT * FROM $table WHERE $key $where;
SQL;

                    \DB::statement($sql);

                    $this->info('< SQL > ' . $sql);
                    $this->info('[ ' . $table . ' ] 테이블이 복사 되었습니다');

                    $sql = <<<SQL
DELETE FROM $table WHERE $key $where;
SQL;

                    \DB::statement($sql);

                    $this->info('< SQL > ' . $sql);
                    $this->info('[ ' . $table . ' ] 테이블에서 복사된 데이터를 삭제하였습니다');


                    $copied_tables[] = $table;
                }
            }

            /**
             * 하위 테이블 처리 (개별 키)
             */

            // 복사된 테이블들과 개별 키로 상속 관계를 갖고 있는 테이블들
            $etc_tables = [
                [
                    'target_table' => 'oauth_refresh_tokens',
                    'target_key' => 'access_token_id',
                    'from_table' => 'oauth_access_tokens',
                    'from_key' => 'access_token_id'
                ],
                [
                    'target_table' => 'promotion_overlap',
                    'target_key' => 'no_basis_seq',
                    'from_table' => 'promotion_deal',
                    'from_key' => 'no_deal'
                ],
                [
                    'target_table' => 'coupon_event_condition',
                    'target_key' => 'no_event',
                    'from_table' => 'coupon_event',
                    'from_key' => 'no_event'
                ],
                [
                    'target_table' => 'retail_coupon_event_usepartner',
                    'target_key' => 'no_event',
                    'from_table' => 'retail_coupon_event',
                    'from_key' => 'no'
                ],
                [
                    'target_table' => 'hand_wash_coupon_event_condition',
                    'target_key' => 'no_event',
                    'from_table' => 'hand_wash_coupon_event',
                    'from_key' => 'no_event'
                ],
                [
                    'target_table' => 'personal_access_tokens',
                    'target_key' => 'token',
                    'from_table' => 'member_detail',
                    'from_key' => 'ds_access_token_rsm'
                ]
            ];

            foreach ($etc_tables as $etc_table) {
                $this->info('[ ' . $etc_table['target_table'] . ' ] 테이블을 복사합니다');

                $sql = <<<SQL
INSERT INTO $schema.{$etc_table['target_table']}
    SELECT * FROM {$etc_table['target_table']}
    WHERE {$etc_table['target_key']} IN
        (SELECT {$etc_table['from_key']} FROM $schema.{$etc_table['from_table']});
SQL;

                \DB::statement($sql);

                $this->info('< SQL > ' . $sql);
                $this->info('[ ' . $etc_table['target_table'] . ' ] 테이블이 복사 되었습니다');

                $sql = <<<SQL
DELETE FROM {$etc_table['target_table']}
    WHERE {$etc_table['target_key']} IN
        (SELECT {$etc_table['from_key']} FROM $schema.{$etc_table['from_table']});
SQL;

                \DB::statement($sql);

                $this->info('< SQL > ' . $sql);
                $this->info('[ ' . $etc_table['target_table'] . ' ] 테이블에서 복사된 데이터를 삭제하였습니다');

            }


            /**
             * 스키마별 개별 처리
             */
            if ($schema === 'gtcs') {
                $this->info('== [ ' . $schema . ' ] 스키마를 위한 개별 처리를 시작합니다 ==');

                // gtcs_bbs_* 테이블 데이터 복사 (terms 제외)
                $gtcs_bbs_tables = ['gtcs_bbs_event', 'gtcs_bbs_faq', 'gtcs_bbs_notice'];

                foreach ($gtcs_bbs_tables as $gtcs_bbs_table) {
                    $this->info('[ ' . $gtcs_bbs_table . ' ] 테이블을 복사합니다');
                    $target_table = str_replace('gtcs_', '', $gtcs_bbs_table);

                    $this->info('[ ' . $target_table . ' ] 복사전 테이블을 초기화 합니다');
                    $sql = <<<SQL
DELETE FROM $schema.$target_table;
SQL;
                    \DB::statement($sql);

                    $sql = <<<SQL
INSERT INTO $schema.$target_table SELECT * FROM $gtcs_bbs_table;
SQL;

                    \DB::statement($sql);

                    $this->info('< SQL > ' . $sql);
                    $this->info('[ ' . $gtcs_bbs_table . ' ] 테이블이 복사 되었습니다');
                }

                // gtcs_bbs_terms 작업
                // bbs_terms 와 구조적 차이가 있어서 별도로 처리
                // gtcs_bbs_terms 의 terms_category 로 bbs_terms_category 를 참조한다
                $this->info('[ gtcs_bbs_terms ] 테이블을 복사합니다');

                $this->info('[ bbs_terms ] 복사전 테이블을 초기화 합니다');
                $sql = <<<SQL
DELETE FROM $schema.bbs_terms;
SQL;
                \DB::statement($sql);

                // bbs_terms 삽입
                $sql = <<<SQL
INSERT INTO $schema.bbs_terms SELECT gbt.no, gbt.terms_category, btc.nm_terms_category, gbt.no_version, gbt.ds_content, gbt.yn_show, gbt.id_upt, gbt.dt_upt, gbt.id_del, gbt.dt_del, gbt.id_reg, gbt.dt_reg FROM gtcs_bbs_terms gbt LEFT JOIN bbs_terms_category btc ON gbt.terms_category = btc.terms_category
SQL;
                \DB::statement($sql);

                $this->info('[ gtcs_bbs_terms_category ] 테이블을 복사합니다');

                $this->info('[ terms_category ] 복사전 테이블을 초기화 합니다');

                $sql = <<<SQL
DELETE FROM $schema.bbs_terms_category;
SQL;
                \DB::statement($sql);

                // bbs_terms_category 삽입
                $sql = <<<SQL
INSERT INTO $schema.bbs_terms_category SELECT * FROM bbs_terms_category
SQL;
                \DB::statement($sql);

                $this->info('[ gtcs_bbs_terms_category ] 테이블이 복사 되었습니다');


                // oauth_clients 값 추가
                // 현재 owin 과 같은 값을 쓰고 있음
                $this->info('[ oauth_clients ] 테이블을 복사합니다');

                $this->info('[ oauth_clients ] 복사전 테이블을 초기화 합니다');

                $sql = <<<SQL
DELETE FROM $schema.oauth_clients;
SQL;
                \DB::statement($sql);

                $sql = <<<SQL
INSERT INTO $schema.oauth_clients SELECT * FROM oauth_clients;
SQL;

                \DB::statement($sql);

                $this->info('[ oauth_clients ] 테이블이 복사 되었습니다');


                // 관리자 복사 (일단 전체 복사)
                $this->info('[ administrator ] 테이블을 복사합니다');
                $this->info('[ administrator ] 복사전 테이블을 초기화 합니다');

                $sql = <<<SQL
DELETE FROM $schema.administrator;
SQL;

                \DB::statement($sql);


                $sql = <<<SQL
INSERT INTO $schema.administrator SELECT * FROM administrator;
SQL;

                \DB::statement($sql);

                $this->info('[ administrator ] 테이블이 복사 되었습니다');

            } else if ($schema === 'renault') {
                $this->info('== [ ' . $schema . ' ] 스키마를 위한 개별 처리를 시작합니다 ==');
            } else if ($schema === 'tmap') {
                $this->info('== [ ' . $schema . ' ] 스키마를 위한 개별 처리를 시작합니다 ==');
            }

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } finally {
            Storage::disk('public')->put(now().'.log',$this->log);
        }

        return Command::SUCCESS;
    }

    /**
     * 특정 컬럼을 갖고 있는 뷰가 아닌 테이블만 조회
     * @param $column
     * @param $schema
     * @return array
     */
    private function getBaseTablesHavingColumn($column, $schema) {
        $sql = <<<SQL
SELECT DISTINCT TABLE_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE COLUMN_NAME IN ('$column')
        AND TABLE_SCHEMA='$schema'
        AND TABLE_NAME IN
        (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE <> 'VIEW' AND TABLE_SCHEMA='$schema')
SQL;

        $tables = \DB::select($sql);
        return array_column($tables, 'TABLE_NAME');
    }

}
