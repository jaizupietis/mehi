
#!/bin/bash
cd /path/to/your/project
php telegram_polling.php &
echo $! > telegram_polling.pid
echo "Telegram polling started with PID: $(cat telegram_polling.pid)"
