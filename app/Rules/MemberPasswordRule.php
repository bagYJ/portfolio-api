<?php

namespace App\Rules;

use App\Exceptions\OwinException;
use App\Models\User;
use App\Utils\Code;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class MemberPasswordRule implements Rule
{
    public $message;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Regex from frontend
        // 공백 X: /^\S*$/
        // 한글 X: /^[^\uAC00-\uD7A3\u3131-\u3163\u4e00-\u9FA5]+$/
        // 기본: /^(?=.*[a-zA-Z])(?=.*\d)(?=.*[~!@#$%^&*()_+])[a-zA-Z\d~!@#$%^&*()_+]{8,20}$/

        // 가입시에는 id_user 전달됨
        $id_user = request()->input('id_user');
        // 비밀번호 변경시에는 no_user 전달됨
        $no_user = request()->input('no_user');

        if ($id_user && $id_user === $value) {
            $this->message = '아이디는 비밀번호로 사용할 수 없어요';
            return false;
        }

        if ($no_user) {
            $user = User::find($no_user);
            if (!$user) {
                throw new OwinException(Code::message('M1305'));
            }

            if ($user->id_user === $value) {
                $this->message = '아이디는 비밀번호로 사용할 수 없어요';
                return false;
            }
        }

        $patterns = [
            [
                'name' => 'no_space',
                'pattern' => '/^\S*$/',
                'message' => '비밀번호에는 공백을 포함할 수 없어요'
            ],
            [
                'name' =>'no_korean',
                'pattern' => '/^[^\x{AC00}-\x{D7A3}\x{3131}-\x{3163}\x{4e00}-\x{9FA5}]+$/u',
                'message' => '비밀번호에는 한글을 포함할 수 없어요'
            ],
            [
                'name' => 'default',
                'pattern' => '/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[~!@#$%^&*()_+])[a-zA-Z\d~!@#$%^&*()_+]{8,20}$/',
                'message' => '*영문, 숫자, 특수문자 조합 8~20자리'
            ]
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern['pattern'], $value)) {
                $this->message = $pattern['message'];
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
