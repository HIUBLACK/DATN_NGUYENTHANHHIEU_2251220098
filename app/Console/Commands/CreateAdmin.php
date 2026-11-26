<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Helper\TerminalInputHelper;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Tạo tài khoản quản trị viên thủ công với mật khẩu nhập ẩn';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Vui lòng chạy tương tác để nhập mật khẩu an toàn.');

            return self::FAILURE;
        }

        // Symfony tạm đổi code page khi đọc input Windows. Nạp trước các class
        // được dùng trong khoảng đó để autoload không đọc sai đường dẫn có dấu.
        if (PHP_OS_FAMILY === 'Windows') {
            class_exists(TerminalInputHelper::class);
            class_exists(MissingInputException::class);
        }

        $codePage = function_exists('sapi_windows_cp_get') ? sapi_windows_cp_get() : null;
        try {
            $input = [
                'name' => trim((string) $this->ask('Họ tên')),
                'email' => mb_strtolower(trim((string) $this->ask('Email'))),
            ];
        } finally {
            // Khôi phục cả khi EOF/exception xảy ra trước khi Symfony tự khôi phục.
            if ($codePage !== null) {
                sapi_windows_cp_set($codePage);
            }
        }
        $identity = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng. Không thay đổi tài khoản hiện có.',
            'max' => ':attribute không được vượt quá :max ký tự.',
        ]);
        if ($identity->fails()) {
            foreach ($identity->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // Không cho phép fallback hiện mật khẩu nếu terminal không hỗ trợ nhập ẩn.
        $input['password'] = $this->secret('Mật khẩu (ít nhất 8 ký tự, có chữ và số)', false);
        $input['password_confirmation'] = $this->secret('Xác nhận mật khẩu', false);
        $password = Validator::make($input, [
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu chưa khớp.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 255 ký tự.',
            'password.letters' => 'Mật khẩu cần có chữ cái.',
            'password.numbers' => 'Mật khẩu cần có chữ số.',
        ]);
        if ($password->fails()) {
            foreach ($password->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $admin = new User;
            $admin->name = $input['name'];
            $admin->email = $input['email'];
            $admin->password = Hash::make($input['password']);
            $admin->has_local_password = true;
            $admin->role = UserRole::Admin;
            $admin->status = UserStatus::Active;
            $admin->save();
        } catch (UniqueConstraintViolationException $exception) {
            $this->error('Email đã được sử dụng. Không thay đổi tài khoản hiện có.');

            return self::FAILURE;
        }

        $this->info('Đã tạo tài khoản Admin. Đăng nhập tại /login.');

        return self::SUCCESS;
    }
}
