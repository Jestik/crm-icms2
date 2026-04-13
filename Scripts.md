## Полезные скрипты, которые работают просто при использовании их в виджетах или на странице.

### QR сканер, работает на телефонах
```javascript
<script src="https://unpkg.com/html5-qrcode"></script>

<style>
    .qr-wrapper {
        font-family: inherit;
        max-width: 450px;
        margin: 0 auto; 
        padding: 24px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); 
        box-sizing: border-box;
    }

    .qr-title {
        text-align: center;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: 600;
        color: inherit; 
    }

    #reader {
        width: 100%;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e2e8f0 !important;  
    }

    
    #reader button {
        background-color: #4361ee; 
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s;
        margin: 5px;
    }

    #reader button:hover {
        background-color: #3f37c9;
    }

    #reader a {
        display: none !important; 
    }

    .qr-message {
        margin-top: 15px;
        padding: 12px;
        text-align: center;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        display: none; 
    }

    .qr-message.success {
        display: block;
        background-color: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }

    .qr-message.error {
        display: block;
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
</style>

<div class="qr-wrapper">
    <div id="reader"></div>
    <div id="message" class="qr-message"></div>
</div>

<script>
    function onScanSuccess(decodedText, decodedResult) {
        html5QrcodeScanner.clear().then(() => {
            const messageDiv = document.getElementById('message');

            if (decodedText.startsWith("http://") || decodedText.startsWith("https://")) {
                messageDiv.className = "qr-message success";
                messageDiv.innerText = "Код распознан! Перенаправляем...";

                window.location.href = decodedText;
            } else {
                messageDiv.className = "qr-message error";
                messageDiv.innerText = "Ошибка: Зашифрована не ссылка.\n" + decodedText;

                setTimeout(() => {
                    messageDiv.style.display = 'none';
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                }, 3000);
            }
        }).catch(error => {
            console.error("Не удалось остановить сканер.", error);
        });
    }

    function onScanFailure(error) {
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            showTorchButtonIfSupported: true // Добавит кнопку фонарика, если телефон поддерживает
        },
        false
    );

    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
```
