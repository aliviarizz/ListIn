<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<title>Bot Manager - List In</title>
<style>
    /* Style tetap sama seperti sebelumnya, tidak perlu diubah */
    .chatbot-page-container { display: flex; flex-direction: column; height: calc(90vh - 55px - 30px); max-width: 800px; margin: 0 auto; background-color: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); overflow: hidden; }
    html.dark-theme-active .chatbot-page-container { background: #2c2c2c; border: 1px solid #444; }
    .chat-header { padding: 12px 20px; background-color: #7e47b8; color: white; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; }
    html.dark-theme-active .chat-header { background-color: #bb86fc; color: #121212; }
    .chat-messages { flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
    .chat-messages::-webkit-scrollbar { display: none; }
    .message-wrapper { display: flex; flex-direction: column; max-width: 85%; }
    .message-wrapper.sender-ai { align-self: flex-start; }
    .message-wrapper.sender-user { align-self: flex-end; }
    .sender-name { font-size: 0.75rem; color: #888; margin-bottom: 4px; padding: 0 10px; }
    html.dark-theme-active .sender-name { color: #aaa; }
    .sender-user .sender-name { text-align: right; }
    .message { padding: 10px 15px; border-radius: 18px; line-height: 1.5; word-wrap: break-word; }
    .message.ai { background-color: #f1f0f0; color: #3c4250; border-top-left-radius: 5px; }
    html.dark-theme-active .message.ai { background-color: #3a3a3a; color: #e0e0e0;}
    .message.user { background-color: #7e47b8; color: #ffffff; border-top-right-radius: 5px; }
    html.dark-theme-active .message.user { background-color: #bb86fc; color: #121212; }
    .typing-indicator span { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: #ccc; margin: 0 2px; animation: bounce 1.4s infinite ease-in-out both; }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    .chat-input-area { display: flex; padding: 15px; border-top: 1px solid #e7eaec; flex-shrink: 0; }
    html.dark-theme-active .chat-input-area { border-top-color: #444; }
    .chat-input-area input { flex-grow: 1; border: 1px solid #d1d5db; border-radius: 20px; padding: 10px 18px; font-size: 0.9rem; background-color: #f9fafb; }
    html.dark-theme-active .chat-input-area input { background-color: #373737; border-color: #555; color: #e0e0e0; }
    .chat-input-area button { background: #7e47b8; border: none; border-radius: 50%; width: 40px; height: 40px; margin-left: 10px; cursor: pointer; display: flex; justify-content: center; align-items: center; color: white; }
    html.dark-theme-active .chat-input-area button { background: #bb86fc; }
    .cards-container { display: flex; flex-direction: column; gap: 10px; margin-top: 10px;}
    .confirmation-card { border: 1px solid #e7eaec; border-radius: 8px; padding: 12px; background: #fafafa; transition: all 0.3s ease; }
    html.dark-theme-active .confirmation-card { background: #373737; border-color: #555; }
    .confirmation-card p { margin: 0 0 8px 0; font-size: 0.9rem; }
    .confirmation-card strong { color: #34495e; }
    html.dark-theme-active .confirmation-card strong { color: #f5f5f5; }
    .confirmation-actions { display: flex; gap: 8px; margin-top: 10px; }
    .card-result-message { font-size: 0.8rem; margin-top: 8px; font-weight: 500; }
    .card-result-message.success { color: #28a745; }
    .card-result-message.error { color: #dc3545; }
</style>

<main class="main">
    <div class="chatbot-page-container">
        <div class="chat-header">Bot Manager</div>
        <div class="chat-messages" id="chatMessages"></div>
        <div class="chat-input-area">
            <input type="text" id="userInput" placeholder="Ketik perintah Anda di sini..." autocomplete="off" />
            <button id="sendButton" title="Kirim"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatMessages = document.getElementById("chatMessages");
    const userInput = document.getElementById("userInput");
    const sendButton = document.getElementById("sendButton");
    let conversationHistory = [];
    const storedSuggestions = new Map(); // Untuk menyimpan data saran

    function addMessageToChat(text, sender, isHTML = false) {
        const wrapper = document.createElement("div");
        wrapper.classList.add("message-wrapper", `sender-${sender}`);
        wrapper.innerHTML = `<div class="sender-name">${sender === 'ai' ? 'Bot Manager' : 'Anda'}</div><div class="message ${sender}"></div>`;
        const messageDiv = wrapper.querySelector('.message');
        if (isHTML) {
            messageDiv.innerHTML = text;
        } else {
            messageDiv.textContent = text;
        }
        chatMessages.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    }

    function showTypingIndicator() {
        const wrapper = addMessageToChat('', 'ai');
        wrapper.querySelector('.message').innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
        return wrapper;
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // =========================================================================
    // ==========            MODIFIKASI UTAMA DI SINI            ==========
    // =========================================================================
    function displayConfirmationCards(baseMessage, suggestions) {
        // AI mungkin mengembalikan satu objek atau array objek. Standarkan menjadi array.
        const suggestionsArray = Array.isArray(suggestions) ? suggestions : [suggestions];
        
        // Buat HTML untuk setiap kartu konfirmasi
        let cardsHTML = suggestionsArray.map((suggestion, index) => {
            const cardId = `card-${Date.now()}-${index}`;
            storedSuggestions.set(cardId, suggestion); // Simpan data lengkap dengan ID unik

            let title = '';
            let detailsHTML = '';
            const intent = suggestion.intent;

            if (intent === "create_suggestion") {
                title = `Buat Tugas: "${suggestion.title}"`;
                detailsHTML = `<p style="font-size: 0.8rem; margin:0;">Prioritas: ${suggestion.priority || 'Medium'}, Deadline: ${suggestion.due_date || 'N/A'}</p>`;
            } else if (intent === "update_suggestion") {
                title = `Perbarui Tugas: "${suggestion.title_original}"`;
                const updates = Object.entries(suggestion.updates).map(([key, value]) => `${key}: ${value}`).join(', ');
                detailsHTML = `<p style="font-size: 0.8rem; margin:0;">Perubahan: ${updates}</p>`;
            } else if (intent === "delete_suggestion") {
                title = `Hapus Tugas: "${suggestion.title}"`;
            }

            return `
                <div class="confirmation-card" id="${cardId}">
                    <p><strong>${title}</strong></p>
                    ${detailsHTML}
                    <div class="confirmation-actions">
                        <button class="btn btn-secondary btn-sm cancel-action">Batal</button>
                        <button class="btn btn-primary btn-sm confirm-action">Lanjutkan</button>
                    </div>
                </div>`;
        }).join('');
        
        const messageDiv = addMessageToChat(`${baseMessage.replace(/\n/g, "<br>")}<div class="cards-container">${cardsHTML}</div>`, "ai", true);
        
        // Tambahkan event listener ke penampung kartu
        messageDiv.querySelector('.cards-container').addEventListener('click', (e) => {
            const button = e.target.closest('button.confirm-action, button.cancel-action');
            if (!button) return;

            const card = e.target.closest('.confirmation-card');
            const isConfirmed = button.classList.contains('confirm-action');
            
            // Nonaktifkan tombol hanya di kartu yang diklik
            card.querySelectorAll('button').forEach(btn => btn.disabled = true);
            card.style.opacity = '0.7';

            if (isConfirmed) {
                const suggestionToConfirm = storedSuggestions.get(card.id);
                handleConfirmation(suggestionToConfirm, card);
            } else {
                card.querySelector('.confirmation-actions').innerHTML = '<p class="card-result-message">Dibatalkan.</p>';
            }
        });
    }

    async function handleConfirmation(suggestion, cardElement) {
        try {
            const response = await fetch("gemini_handler.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action: "confirm_crud_action",
                    suggestion: suggestion // Kirim satu objek saran saja
                })
            });
            const data = await response.json();
            
            const resultP = document.createElement('p');
            resultP.classList.add('card-result-message');
            resultP.textContent = data.message;

            if (data.success) {
                resultP.classList.add('success');
                cardElement.style.borderColor = '#28a745';
            } else {
                resultP.classList.add('error');
                cardElement.style.borderColor = '#dc3545';
            }
            cardElement.querySelector('.confirmation-actions').replaceWith(resultP);

        } catch (error) {
            console.error("Confirmation Error:", error);
            const resultP = document.createElement('p');
            resultP.classList.add('card-result-message', 'error');
            resultP.textContent = 'Gagal menghubungi server.';
            cardElement.querySelector('.confirmation-actions').replaceWith(resultP);
        }
    }
    
    async function sendMessage() {
        const userText = userInput.value.trim();
        if (!userText) return;

        addMessageToChat(userText, "user");
        userInput.value = "";
        userInput.focus();
        conversationHistory.push({ role: "user", parts: [{ text: userText }] });
        const typingIndicator = showTypingIndicator();

        try {
            const response = await fetch("gemini_handler.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "chat_with_ai", history: conversationHistory })
            });
            typingIndicator.remove();

            if (!response.ok) throw new Error(`Server Error: ${response.statusText} - ${await response.text()}`);

            const data = await response.json();
            if (data.success) {
                const fullAiResponseText = data.ai_message + (data.suggestions_array ? ` [SUGGESTION_START]${JSON.stringify(data.suggestions_array)}[SUGGESTION_END]` : '');
                conversationHistory.push({ role: "model", parts: [{ text: fullAiResponseText }] });

                if (data.suggestions_array && data.suggestions_array.length > 0) {
                    displayConfirmationCards(data.ai_message, data.suggestions_array);
                } else {
                    addMessageToChat(data.ai_message, "ai");
                }
            } else {
                addMessageToChat(data.message || "Terjadi kesalahan.", "ai");
            }
        } catch (error) {
            if(typingIndicator) typingIndicator.remove();
            addMessageToChat("Koneksi gagal atau terjadi kesalahan. Silakan coba lagi.", "ai");
            console.error("Send Message Error:", error);
        }
    }

    sendButton.addEventListener("click", sendMessage);
    userInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    addMessageToChat("Halo! Saya adalah Bot Manager. Anda dapat meminta saya untuk membuat, mengubah, atau menghapus tugas. Contoh: 'Buatkan tugas selesaikan laporan dan beli susu'.", "ai");
});
</script>

<?php require_once 'includes/footer.php'; ?>
