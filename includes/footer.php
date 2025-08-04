</div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. Visas tiesības aizsargātas.</p>
            <p><small>Task Management sistēma v1.0 | Pēdējā atjaunošana: <?php echo date('d.m.Y'); ?></small></p>
        </div>
    </footer>
    
    <!-- JavaScript funkcionalitāte -->
    <script>
        // Globālās funkcijas
        
        // Modal logs
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Aizvērt modālos logus ar ESC taustiņu
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
        
        // Aizvērt modālo logu, klikšķinot uz fona
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
        
        // Apstiprināšanas dialogs
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Uzdevuma statusa maiņa
        function changeTaskStatus(taskId, newStatus) {
            if (confirm('Vai tiešām vēlaties mainīt uzdevuma statusu?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const taskInput = document.createElement('input');
                taskInput.type = 'hidden';
                taskInput.name = 'task_id';
                taskInput.value = taskId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'change_status';
                
                form.appendChild(taskInput);
                form.appendChild(statusInput);
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Darba laika uzsākšana/beigšana
        function toggleWorkTime(taskId, action) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const taskInput = document.createElement('input');
            taskInput.type = 'hidden';
            taskInput.name = 'task_id';
            taskInput.value = taskId;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            
            form.appendChild(taskInput);
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-refresh funkcionalitāte (katras 30 sekundes)
        function enableAutoRefresh() {
            setInterval(function() {
                // Atjaunot paziņojumu skaitītāju
                fetch('ajax/get_notification_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-badge .badge');
                        if (badge) {
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => console.log('Kļūda atjaunojot paziņojumus:', error));
            }, 30000);
        }
        
        // Filtru funkcionalitāte
        function applyFilters() {
            const form = document.getElementById('filterForm');
            if (form) {
                form.submit();
            }
        }
        
        function clearFilters() {
            const form = document.getElementById('filterForm');
            if (form) {
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
                form.submit();
            }
        }
        
        // Kārtošanas funkcionalitāte
        function sortBy(column, direction) {
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', direction);
            window.location = url;
        }
        
        // Failu augšupielādes progress
        function handleFileUpload(input) {
            const files = input.files;
            const maxSize = <?php echo MAX_FILE_SIZE; ?>;
            const allowedTypes = <?php echo json_encode(ALLOWED_FILE_TYPES); ?>;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (file.size > maxSize) {
                    alert(`Fails "${file.name}" ir pārāk liels. Maksimālais izmērs: ${Math.round(maxSize / 1024 / 1024)}MB`);
                    input.value = '';
                    return false;
                }
                
                const extension = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(extension)) {
                    alert(`Faila tips "${extension}" nav atļauts. Atļautie tipi: ${allowedTypes.join(', ')}`);
                    input.value = '';
                    return false;
                }
            }
            
            return true;
        }
        
        // Inicializēt sistēmu
        document.addEventListener('DOMContentLoaded', function() {
            enableAutoRefresh();
            
            // Pievienot file upload validāciju
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    handleFileUpload(this);
                });
            });
            
            // Pievienot tooltips
            const elements = document.querySelectorAll('[title]');
            elements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.title;
                    tooltip.style.cssText = `
                        position: absolute;
                        background: rgba(0,0,0,0.8);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 12px;
                        z-index: 1000;
                        pointer-events: none;
                    `;
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
                    
                    this.tooltipElement = tooltip;
                });
                
                element.addEventListener('mouseleave', function() {
                    if (this.tooltipElement) {
                        document.body.removeChild(this.tooltipElement);
                        this.tooltipElement = null;
                    }
                });
            });
        });
        
        // Push notification funkcionalitāte (ja atbalstīta)
        if ('Notification' in window && 'serviceWorker' in navigator) {
            Notification.requestPermission();
        }
        
        function showNotification(title, body, icon = '/favicon.ico') {
            if (Notification.permission === 'granted') {
                new Notification(title, {
                    body: body,
                    icon: icon,
                    badge: icon
                });
            }
        }
    </script>
    
    <?php if (isset($additionalJS)): ?>
        <?php echo $additionalJS; ?>
    <?php endif; ?>
</body>
</html>