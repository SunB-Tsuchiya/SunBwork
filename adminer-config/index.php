<?php

/**
 * Custom Adminer with SQLite support and predefined credentials
 */

function adminer_object()
{
    // SQLiteログインプラグインクラス
    class AdminerSoftware extends Adminer
    {

        function login($login, $password)
        {
            // 指定されたユーザー名とパスワードをチェック
            return ($login == "admin" && $password == "admin123");
        }

        function credentials()
        {
            // SQLiteの場合は空の認証情報を返す
            return array($_GET["server"], $_GET["username"], get_password());
        }

        function database()
        {
            // データベース名を返す（SQLiteの場合はファイルパス）
            return $_GET["server"];
        }

        function loginForm()
        {
?>
            <table cellspacing="0">
                <tr>
                    <th>System</th>
                    <td>
                        <select name="auth[driver]">
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Server</th>
                    <td>
                        <input name="auth[server]" value="/var/www/html/database/database.sqlite"
                            title="SQLite database file path" readonly>
                    </td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td>
                        <input name="auth[username]" id="username" value="admin"
                            placeholder="Enter: admin">
                    </td>
                </tr>
                <tr>
                    <th>Password</th>
                    <td>
                        <input type="password" name="auth[password]"
                            placeholder="Enter: admin123">
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" value="Login">
                <label>
                    <input type="checkbox" name="auth[permanent]" value="1">
                    Permanent login
                </label>
            </p>
            <p><strong>認証情報:</strong></p>
            <ul>
                <li>ユーザー名: <code>admin</code></li>
                <li>パスワード: <code>admin123</code></li>
            </ul>
<?php
        }
    }

    return new AdminerSoftware;
}

include "./adminer.php";
?>