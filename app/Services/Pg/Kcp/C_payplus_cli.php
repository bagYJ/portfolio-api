<?php

namespace App\Services\Pg\Kcp;

/* ====================================================================== */
/* =   PAGE : 지불 연동 PHP 라이브러리                                  = */
/* = ------------------------------------------------------------------ = */
/* =   Copyright (c)  2010.02.   KCP Inc.   All Rights Reserverd.       = */
/* ====================================================================== */

/* ====================================================================== */
/* =   지불 연동 CLASS                                                  = */

/* ====================================================================== */

use Illuminate\Support\Facades\Log;

class   C_payplus_cli
{
    public $m_payx_data;
    public $m_ordr_data;
    public $m_rcvr_data;
    public $m_escw_data;
    public $m_modx_data;
    public $m_encx_data;
    public $m_encx_info;

    /* -------------------------------------------------------------------- */
    /* -   처리 결과 값                                                   - */
    /* -------------------------------------------------------------------- */
    public $m_res_data;
    public $m_res_cd;
    public $m_res_msg;

    /* -------------------------------------------------------------------- */
    /* -   생성자                                                         - */
    /* -------------------------------------------------------------------- */
    public function __construct()//lib추가
    {
        // Assign the CodeIgniter super-object

    }

    public function C_payplus_cli()
    {
        $this->m_payx_data = "payx_data=";
        $this->m_payx_common = "";
        $this->m_payx_card = "";
        $this->m_ordr_data = "";
        $this->m_rcvr_data = "";
        $this->m_escw_data = "";
        $this->m_modx_data = "";
        $this->m_encx_data = "";
    }

    public function mf_init($mode)
    {
        if ($mode == "1") {
            if (!extension_loaded('pp_cli_dl_php')) {
                dl("pp_cli_dl_php.so");
            }
        }
        echo "C_PAYPLUS_CLIC_PAYPLUS_CLI";
    }

    public function mf_clear()
    {
        $this->m_payx_data = "payx_data=";
        $this->m_payx_common = "";
        $this->m_payx_card = "";
        $this->m_ordr_data = "";
        $this->m_rcvr_data = "";
        $this->m_escw_data = "";
        $this->m_modx_data = "";
        $this->m_encx_data = "";
    }

    public function mf_set_data_us($name, $val)
    {
        $data = "";

        if ($name != "" && $val != "") {
            $data = $name . '=' . $val . chr(31);
        }

        return $data;
    }

    public function mf_add_payx_data($pay_type, $payx_data)
    {
        $this->m_payx_data .= ($pay_type . '=' . $payx_data . chr(30));
    }

    public function mf_set_ordr_data($name, $val)
    {
        if ($val != "") {
            $this->m_ordr_data .= ($name . '=' . $val . chr(31));
        }
    }

    public function mf_set_rcvr_data($name, $val)
    {
        if ($val != "") {
            $this->m_rcvr_data .= ($name . '=' . $val . chr(31));
        }
    }

    public function mf_set_escw_data($name, $val)
    {
        if ($val != "") {
            $this->m_escw_data .= ($name . '=' . $val . chr(29));
        }
    }

    public function mf_set_modx_data($name, $val)
    {
        if ($val != "") {
            $this->m_modx_data .= ($name . '=' . $val . chr(31));
        }
    }

    /* -------------------------------------------------------------------- */
    /* -   FUNC  :  지불 처리 함수                                        - */
    /* -------------------------------------------------------------------- */
    public function mf_do_tx(
        $trace_no,
        $home_dir,
        $site_cd,
        $site_key,
        $tx_cd,
        $pub_key_str,
        $pa_url,
        $pa_port,
        $user_agent,
        $ordr_idxx,
        $cust_ip,
        $log_level,
        $opt,
        $mode,
        $g_conf_log_path
    ) {
        $payx_data = $this->m_payx_data;

        $ordr_data = $this->mf_get_data("ordr_data", $this->m_ordr_data);
        $rcvr_data = $this->mf_get_data("rcvr_data", $this->m_rcvr_data);
        $escw_data = $this->mf_get_data("escw_data", $this->m_escw_data);
        $modx_data = $this->mf_get_data("mod_data", $this->m_modx_data);

        $res_data = $this->mf_exec(
            $home_dir . "/bin/pp_cli",
            "-h",
            "home=" . $home_dir . "," .
            "site_cd=" . $site_cd . "," .
            "site_key=" . $site_key . "," .
            "tx_cd=" . $tx_cd . "," .
            "pa_url=" . $pa_url . "," .
            "pa_port=" . $pa_port . "," .
            "ordr_idxx=" . $ordr_idxx . "," .
            "payx_data=" . $payx_data . "," .
            "ordr_data=" . $ordr_data . "," .
            "rcvr_data=" . $rcvr_data . "," .
            "escw_data=" . $escw_data . "," .
            "modx_data=" . $modx_data . "," .
            "enc_data=" . $this->m_encx_data . "," .
            "enc_info=" . $this->m_encx_info . "," .
            "trace_no=" . $trace_no . "," .
            "cust_ip=" . $cust_ip . "," .
            "log_level=" . $log_level . "," .
            "log_path=" . $g_conf_log_path . "," .
            "opt=" . $opt
        );

        if ($res_data == "") {
            $res_data = "res_cd=9502" . chr(31) . "res_msg=연동 모듈 호출 오류";
        }


        parse_str(str_replace(chr(31), "&", $res_data), $this->m_res_data);

        $this->m_res_cd = $this->m_res_data["res_cd"];
        $this->m_res_msg =
        $this->m_res_data["res_msg"] =  iconv('EUC-KR', 'UTF-8', data_get($this->m_res_data, 'res_msg'));
        $this->m_res_data["card_name"] =  iconv('EUC-KR', 'UTF-8', data_get($this->m_res_data, 'card_name'));
        $this->m_res_data["acqu_name"] =  iconv('EUC-KR', 'UTF-8', data_get($this->m_res_data, 'acqu_name'));
    }

    /* -------------------------------------------------------------------- */
    /* -   FUNC  :  처리 결과 값을 리턴하는 함수                           - */
    /* -------------------------------------------------------------------- */
    public function mf_get_res_data($name)
    {
        return $this->m_res_data[$name] ?? '';
    }

    public function mf_get_payx_data()
    {
        $my_data = "";

        if ($this->m_payx_common != "" || $this->m_payx_card != "") {
            $my_data = "payx_data=";
        }

        if ($this->m_payx_common != "") {
            $my_data .= "common=" . $this->m_payx_common . chr(30);
        }

        if ($this->m_payx_card != "") {
            $my_data .= ("card=" . $this->m_payx_card . chr(30));
        }

        return $my_data;
    }

    public function mf_get_data($data_name, $data)
    {
        $my_data = "";

        if ($data != "") {
            $my_data = $data_name . "=" . $data;
        } else {
            $my_data = "";
        }

        return $my_data;
    }

    public function mf_exec()
    {
        $arg = func_get_args();

        if (is_array($arg[0])) {
            $arg = $arg[0];
        }

        $exec_cmd = array_shift($arg);

        foreach ($arg as $i) {
            $exec_cmd .= " " . escapeshellarg($i);
        }
        Log::channel('request')->info('kcp shell script: ', [$exec_cmd]);
        return exec($exec_cmd);
    }
}

?>
