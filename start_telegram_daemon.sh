
#!/bin/bash

# AVOTI Telegram Daemon Script
# Šis skripts palaiž Telegram polling kā daemon procesu

SCRIPT_DIR="/var/www/html/mehi"
PHP_SCRIPT="start_local_telegram.php"
PID_FILE="$SCRIPT_DIR/telegram_daemon.pid"
LOG_FILE="$SCRIPT_DIR/logs/telegram_daemon.log"

# Izveidot log direktoriju, ja nepastāv
mkdir -p "$SCRIPT_DIR/logs"

case "$1" in
    start)
        if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            echo "Telegram daemon jau darbojas (PID: $(cat $PID_FILE))"
            exit 1
        fi
        
        echo "Palaižam Telegram daemon..."
        cd "$SCRIPT_DIR"
        nohup php "$PHP_SCRIPT" >> "$LOG_FILE" 2>&1 &
        echo $! > "$PID_FILE"
        echo "Telegram daemon palaists (PID: $(cat $PID_FILE))"
        ;;
        
    stop)
        if [ -f "$PID_FILE" ]; then
            PID=$(cat "$PID_FILE")
            if kill -0 "$PID" 2>/dev/null; then
                kill "$PID"
                rm -f "$PID_FILE"
                echo "Telegram daemon apturēts"
            else
                echo "Process ar PID $PID neeksistē"
                rm -f "$PID_FILE"
            fi
        else
            echo "PID fails neeksistē, iespējams daemon nedarbojas"
        fi
        ;;
        
    restart)
        $0 stop
        sleep 2
        $0 start
        ;;
        
    status)
        if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            echo "Telegram daemon darbojas (PID: $(cat $PID_FILE))"
            # Parādīt pēdējās log ierakstu līnijas
            echo "Pēdējie log ieraksti:"
            tail -5 "$LOG_FILE"
        else
            echo "Telegram daemon nedarbojas"
            [ -f "$PID_FILE" ] && rm -f "$PID_FILE"
        fi
        ;;
        
    *)
        echo "Lietošana: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
