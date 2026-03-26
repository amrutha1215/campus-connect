/** 
  * Chat System for Campus Portal — Redesigned & Draggable 
  */ 
 const Chat = { 
   currentConvId: null, 
   currentConvTitle: null, 
   chats: [], 
   messages: [], 
   pollInterval: null, 
   replyToId: null, 
   replyToContent: null, 
   typingTimeout: null,
   isDragging: false,
   dragOffset: { x: 0, y: 0 },
   isFullscreen: false,
   isMinimized: false,
   searchTimeout: null,
   socket: null,

   init() { 
     this.cacheDOM(); 
     if (!this.fab) return; 
     this.bindEvents(); 
     this.makeDraggable();
     if (typeof currentUser !== 'undefined' && currentUser) { 
       this.fab.style.display = 'grid'; 
       this.loadChatList();
       this.initSocket();
     } 
   }, 

   initSocket() {
     try {
       this.socket = io("http://localhost:3000", {
         reconnectionAttempts: 3,
         timeout: 5000
       });
       
       this.socket.on("connect", () => {
         console.log("Connected to WebSocket Server");
       });

       this.socket.on("connect_error", (err) => {
         console.warn("WebSocket connection failed. Falling back to polling.");
         this.socket.disconnect();
         this.startPolling();
       });

       this.socket.on("new_msg", (data) => {
         // Only add if it belongs to current conversation
         if (this.currentConvId == data.conversation_id) {
           this.messages.push(data);
           this.renderMessages(true);
         }
         // Refresh list for last message preview
         this.loadChatList();
       });

       this.socket.on("user_typing", (data) => {
         if (this.currentConvId == data.convId) {
           this.activeChatStatus.textContent = data.isTyping ? `${data.user} is typing...` : (this.chats.find(c => c.id == this.currentConvId)?.type === 'group' ? 'Group Chat' : 'Direct Message');
           this.activeChatStatus.style.color = data.isTyping ? 'var(--accent)' : 'var(--muted)';
         }
       });

       this.socket.on("global_notification", (data) => {
         // Show notification if it's for this user or for everyone
         if (!data.user_id || data.user_id == currentUser.id) {
           if (typeof NotifManager !== 'undefined') {
             NotifManager.send(data.title, { body: data.message });
           }
           if (typeof loadNotifications === 'function') loadNotifications();
         }
       });

     } catch (e) {
       console.warn("WebSocket connection failed. Falling back to polling.");
       this.startPolling(); // Fallback
     }
   },

   cacheDOM() { 
     this.fab        = document.getElementById('chatFab'); 
     this.drawer     = document.getElementById('chatDrawer'); 
     this.chatListContainer = document.getElementById('chatListContainer'); 
     this.messagePane       = document.getElementById('messagePane'); 
     this.chatListView      = document.getElementById('chatListView'); 
     this.messageList       = document.getElementById('messageList'); 
     this.chatInput         = document.getElementById('chatInput'); 
     this.chatSendBtn       = document.getElementById('chatSendBtn'); 
     this.chatFileBtn       = document.getElementById('chatFileBtn'); 
     this.chatFileInput     = document.getElementById('chatFileInput'); 
     this.activeChatTitle   = document.getElementById('activeChatTitle'); 
     this.activeChatAvatar  = document.getElementById('activeChatAvatar'); 
     this.activeChatStatus  = document.getElementById('activeChatStatus'); 
     this.userSearchInput   = document.getElementById('userSearchInput');
     this.userSearchResults = document.getElementById('userSearchResults');
     this.chatHeaders       = [document.getElementById('chatHeader'), document.getElementById('messageHeader')];
   }, 

   bindEvents() { 
     this.fab.onclick = () => this.toggleDrawer(); 
     document.getElementById('backToChatList').onclick = () => this.showChatList(); 
     this.chatSendBtn.onclick = () => this.sendMessage(); 
     this.chatInput.addEventListener('keydown', (e) => { 
       if (e.key === 'Enter' && !e.shiftKey) { 
         e.preventDefault(); 
         this.sendMessage(); 
       } 
     }); 
     this.chatInput.addEventListener('input', () => { 
       this.chatInput.style.height = 'auto'; 
       this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px'; 
       
       // Typing Indicator
       if (this.socket && this.currentConvId && this.currentConvId !== 'ai') {
         if (this.typingTimeout) clearTimeout(this.typingTimeout);
         this.socket.emit("typing", { convId: this.currentConvId, userName: currentUser.name, isTyping: true });
         this.typingTimeout = setTimeout(() => {
           this.socket.emit("typing", { convId: this.currentConvId, userName: currentUser.name, isTyping: false });
         }, 2000);
       }
     }); 
     this.chatFileBtn.onclick = () => this.chatFileInput.click(); 
     this.chatFileInput.onchange = () => this.sendMessage(true); 

     // Close search results when clicking outside
     document.addEventListener('click', (e) => {
       if (this.userSearchResults && !this.userSearchResults.contains(e.target) && e.target !== this.userSearchInput) {
         this.userSearchResults.classList.remove('active');
       }
     });

     // Group Creation & Joining Logic
     document.getElementById('createGroupForm')?.addEventListener('submit', async (e) => {
       e.preventDefault();
       const name = document.getElementById('groupName').value.trim();
       if (!name) return;

       try {
         const res = await fetch(`${API_BASE}?action=chat_create_group`, {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({ name, csrf: window.PORTAL_CSRF_TOKEN })
         });
         const data = await res.json();
         if (data.ok) {
           closeModal('createGroupModal');
           document.getElementById('groupName').value = '';
           await this.loadChatList();
           this.openChat(data.conversation_id);
           alert(`Group created! Share this Invite Code with others: ${data.invite_code}`);
         } else {
           showToast(data.message || "Failed to create group", "error");
         }
       } catch (err) { showToast("Connection error", "error"); }
     });

     document.getElementById('joinGroupForm')?.addEventListener('submit', async (e) => {
       e.preventDefault();
       const code = document.getElementById('inviteCode').value.trim();
       if (!code) return;

       try {
         const res = await fetch(`${API_BASE}?action=chat_join_group`, {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({ invite_code: code, csrf: window.PORTAL_CSRF_TOKEN })
         });
         const data = await res.json();
         if (data.ok) {
           closeModal('joinGroupModal');
           document.getElementById('inviteCode').value = '';
           await this.loadChatList();
           this.openChat(data.conversation_id);
           showToast("Joined group successfully!", "success");
         } else {
           showToast(data.message || "Invalid invite code", "error");
         }
       } catch (err) { showToast("Connection error", "error"); }
     });
   }, 

   makeDraggable() {
     this.chatHeaders.forEach(header => {
       if (!header) return;
       header.addEventListener('mousedown', (e) => {
         if (this.isFullscreen || e.target.closest('button')) return;
         this.isDragging = true;
         this.drawer.style.transition = 'none';
         const rect = this.drawer.getBoundingClientRect();
         this.dragOffset.x = e.clientX - rect.left;
         this.dragOffset.y = e.clientY - rect.top;
         header.style.cursor = 'grabbing';
       });
     });

     document.addEventListener('mousemove', (e) => {
       if (!this.isDragging) return;
       const x = e.clientX - this.dragOffset.x;
       const y = e.clientY - this.dragOffset.y;
       
       // Boundary checks
       const maxX = window.innerWidth - 100;
       const maxY = window.innerHeight - 60;
       
       this.drawer.style.left = Math.min(Math.max(0, x), maxX) + 'px';
       this.drawer.style.top = Math.min(Math.max(0, y), maxY) + 'px';
       this.drawer.style.right = 'auto';
       this.drawer.style.bottom = 'auto';
     });

     document.addEventListener('mouseup', () => {
       if (!this.isDragging) return;
       this.isDragging = false;
       this.drawer.style.transition = '';
       this.chatHeaders.forEach(h => h.style.cursor = 'move');
     });
   },

   toggleDrawer() { 
     if (this.isMinimized) {
       this.toggleMinimize();
       return;
     }
     this.drawer.classList.toggle('open'); 
     if (this.drawer.classList.contains('open') && !this.currentConvId) { 
       this.loadChatList(); 
     } 
   }, 

   closeDrawer() { 
     this.drawer.classList.remove('open'); 
     this.drawer.classList.remove('fullscreen');
     this.drawer.classList.remove('minimized');
     this.isFullscreen = false;
     this.isMinimized = false;
     this.stopPolling(); 
   },

   toggleFullscreen() {
     this.isFullscreen = !this.isFullscreen;
     this.drawer.classList.toggle('fullscreen', this.isFullscreen);
     if (this.isFullscreen) {
       this.drawer.classList.remove('minimized');
       this.isMinimized = false;
       this.drawer.style.left = '';
       this.drawer.style.top = '';
     }
   },

   toggleMinimize() {
     this.isMinimized = !this.isMinimized;
     this.drawer.classList.toggle('minimized', this.isMinimized);
     if (this.isMinimized) {
       this.drawer.classList.remove('fullscreen');
       this.isFullscreen = false;
     }
   },

   /* ─── USER SEARCH ─── */
   async searchUsers(query) {
     if (this.searchTimeout) clearTimeout(this.searchTimeout);
     if (query.length < 2) {
       this.userSearchResults.classList.remove('active');
       return;
     }

     this.searchTimeout = setTimeout(async () => {
       try {
         const res = await fetch(`${API_BASE}?action=user_search&q=${encodeURIComponent(query)}`);
         const data = await res.json();
         if (data.ok) {
           this.renderUserSearchResults(data.users);
         }
       } catch (e) { console.error('User search error', e); }
     }, 300);
   },

   renderUserSearchResults(users) {
     if (!users.length) {
       this.userSearchResults.innerHTML = '<div style="padding:1rem; font-size:0.8rem; color:var(--muted); text-align:center;">No users found</div>';
     } else {
       this.userSearchResults.innerHTML = users.map(u => `
         <div class="user-result-item" onclick="startDirectChat(${u.id})">
           <div class="avatar">${initials(u.fullname)}</div>
           <div class="user-result-info">
             <h5>${escapeHtml(u.fullname)}</h5>
             <p>${escapeHtml(u.email)}</p>
           </div>
         </div>
       `).join('');
     }
     this.userSearchResults.classList.add('active');
   },

   /* ─── CHAT LIST ─── */ 
   async loadChatList() { 
     try { 
       const res  = await fetch(`${API_BASE}?action=chat_list`); 
       const data = await res.json(); 
       if (data.ok) { 
         const prevChats = [...this.chats];
         this.chats = data.chats; 

         // Check for new messages in other conversations
         if (prevChats.length > 0 && typeof NotifManager !== 'undefined') {
           this.chats.forEach(chat => {
             const prev = prevChats.find(pc => pc.id === chat.id);
             if (prev && chat.unread_count > prev.unread_count && chat.id !== this.currentConvId) {
               NotifManager.send(`New message in ${chat.title}`, {
                 body: chat.last_msg,
                 tag: `chat-${chat.id}`
               });
             }
           });
         }

         this.renderChatList(); 
       } 
     } catch (e) { console.error('Chat list error', e); } 
   }, 

   renderChatList() { 
     if (!this.chats.length) { 
       this.chatListContainer.innerHTML = ` 
         <div style="text-align:center; padding: 3rem 1.5rem; color: var(--muted);"> 
           <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4;">💬</div> 
           <p style="font-size: 0.85rem; line-height: 1.6;">No conversations yet.<br>Search for a user below to start chatting.</p> 
         </div>`; 
     } else {
       this.chatListContainer.innerHTML = this.chats.map(c => { 
         const hasUnread = c.unread_count > 0; 
         const isGroup   = c.type === 'group'; 
         const timeStr   = c.last_msg_time ? this._relativeTime(c.last_msg_time) : ''; 
         const avatarBg  = isGroup ? 'linear-gradient(135deg, var(--teal), #0099ff)' 
                                    : 'linear-gradient(135deg, var(--accent), var(--accent2))'; 
         return ` 
           <div class="chat-list-item ${this.currentConvId === c.id ? 'active' : ''}" 
                onclick="Chat.openChat(${c.id})"> 
             <div class="chat-avatar" style="background: ${avatarBg};"> 
               ${initials(c.title || '?')} 
             </div> 
             <div class="chat-info" style="flex:1; overflow:hidden;"> 
               <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;"> 
                 <h4 style="${hasUnread ? 'color:var(--text); font-weight:700;' : ''}">${escapeHtml(c.title)}</h4> 
                 ${timeStr ? `<span style="font-size:0.6rem;color:var(--muted);">${timeStr}</span>` : ''} 
               </div> 
               <p style="${hasUnread ? 'color: rgba(232,236,255,0.7);' : ''}">${escapeHtml(c.last_msg || 'No messages yet')}</p> 
             </div> 
             ${hasUnread ? `<div style="width:8px; height:8px; background:var(--accent2); border-radius:50%; margin-left:5px;"></div>` : ''}
           </div>`; 
       }).join(''); 
     }

     // Prepend AI Assistant
     const aiItem = document.createElement('div');
     aiItem.className = `chat-list-item ${this.currentConvId === 'ai' ? 'active' : ''}`;
     aiItem.onclick = () => this.openAIChat();
     aiItem.innerHTML = `
       <div class="chat-avatar" style="background: linear-gradient(135deg, #7c6bff, #b06bff); position: relative;">
         AI
         <span style="position:absolute; bottom:-2px; right:-2px; width:10px; height:10px; background:var(--teal); border-radius:50%; border:2px solid var(--navy-mid);"></span>
       </div>
       <div class="chat-info" style="flex:1; overflow:hidden;">
         <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
           <h4 style="color:var(--text); font-weight:700;">Campus AI Assistant</h4>
           <span style="font-size:0.6rem; color:var(--teal);">Online</span>
         </div>
         <p>How can I help you today?</p>
       </div>
     `;
     this.chatListContainer.prepend(aiItem);
   }, 

   /* ─── AI CHAT ─── */
   async openAIChat() {
     this.currentConvId = 'ai';
     this.activeChatTitle.textContent = 'Campus AI Assistant';
     this.activeChatAvatar.textContent = 'AI';
     this.activeChatAvatar.style.background = 'linear-gradient(135deg, #7c6bff, #b06bff)';
     this.activeChatStatus.textContent = 'Powered by BVRIT AI';
     this.activeChatStatus.style.color = 'var(--teal)';
     
     this.chatListView.style.display = 'none';
     this.messagePane.classList.add('active');
     this.clearReply();
     
     // Initialize with welcome message if empty
     if (this.messages.length === 0 || this.messages[0].sender_id !== 'ai_system') {
       this.messages = [{
         id: 'ai-welcome',
         sender_id: 'ai_system',
         sender_name: 'Campus AI',
         content: "Hello! I'm your Campus Assistant. Ask me anything about events, departments, or portal features!\n\nI can also search for events and community posts for you. Try asking:\n• \"Tell me about the Tech Fest\"\n• \"Find posts about hackathons\"",
         created_at: new Date().toISOString()
       }];
     }
     this.renderMessages(true);
     this.chatInput.focus();
   },

   async sendAIMessage(content) {
     // Optimistic User Message
     const userMsg = {
       id: 'ai-user-' + Date.now(),
       sender_id: currentUser.id,
       sender_name: currentUser.name,
       content: content,
       created_at: new Date().toISOString()
     };
     this.messages.push(userMsg);
     this.renderMessages(true);

     // Typing indicator placeholder
     const typingMsg = {
       id: 'ai-typing',
       sender_id: 'ai_system',
       sender_name: 'Campus AI',
       content: 'Thinking...',
       isTyping: true,
       created_at: new Date().toISOString()
     };
     this.messages.push(typingMsg);
     this.renderMessages(true);

     try {
       const res = await fetch(`${API_BASE}?action=chat_ai`, {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({ message: content, csrf: window.PORTAL_CSRF_TOKEN })
       });
       const data = await res.json();
       
       // Remove typing indicator
       this.messages = this.messages.filter(m => m.id !== 'ai-typing');
       
       if (data.ok) {
         this.messages.push({
           id: 'ai-res-' + Date.now(),
           sender_id: 'ai_system',
           sender_name: 'Campus AI',
           content: data.message,
           created_at: data.created_at
         });
       } else {
         throw new Error('AI Error');
       }
     } catch (e) {
       this.messages = this.messages.filter(m => m.id !== 'ai-typing');
       this.messages.push({
         id: 'ai-err-' + Date.now(),
         sender_id: 'ai_system',
         sender_name: 'Campus AI',
         content: "I'm having trouble connecting right now. Please try again later!",
         created_at: new Date().toISOString()
       });
     }
     this.renderMessages(true);
   }, 

   /* ─── OPEN / CLOSE CONVERSATION ─── */ 
   async openChat(convId) { 
     if (convId === 'ai') return this.openAIChat();
     this.currentConvId   = convId; 
     window.activeChatTag = `chat-${convId}`; // Mark this chat as active context
     const chat           = this.chats.find(c => c.id == convId); 
     if (!chat) return; 
     this.currentConvTitle = chat.title; 
     this.activeChatTitle.textContent  = chat.title; 
     this.activeChatAvatar.textContent = initials(chat.title); 
     this.activeChatAvatar.style.background = chat.type === 'group' 
       ? 'linear-gradient(135deg, var(--teal), #0099ff)' 
       : 'linear-gradient(135deg, var(--accent), var(--accent2))'; 
     
     this.activeChatStatus.textContent = chat.type === 'group' ? 'Group Chat' : 'Direct Message'; 
     this.activeChatStatus.style.color = 'var(--muted)';
     
     this.chatListView.style.display = 'none'; 
     this.messagePane.classList.add('active'); 
     this.clearReply(); 
     await this.loadMessages(); 
     
     // Socket Join
     if (this.socket && this.currentConvId !== 'ai') {
       this.socket.emit("join_chat", this.currentConvId);
     } else {
       this.startPolling(); 
     }

     this.chatInput.focus(); 
     this.userSearchResults.classList.remove('active');
   }, 

   showChatList() { 
     this.stopPolling(); 
     this.currentConvId = null; 
     window.activeChatTag = null; // Clear active chat context
     this.messages = []; // Clear messages when switching back to list
     this.messagePane.classList.remove('active'); 
     this.chatListView.style.display = 'flex'; 
     this.loadChatList(); 
   }, 

   /* ─── MESSAGES ─── */ 
   async loadMessages() { 
     if (!this.currentConvId || this.currentConvId === 'ai') return; 
     try { 
       const res  = await fetch(`${API_BASE}?action=chat_history&id=${this.currentConvId}`); 
       const data = await res.json(); 
       if (data.ok) { 
         const prevCount = this.messages.length; 
         const newMessages = data.messages;
         
         // Desktop Notification for new chat messages
         if (newMessages.length > prevCount && prevCount !== 0) {
           const latest = newMessages[newMessages.length - 1];
           const myId = typeof currentUser !== 'undefined' ? currentUser.id : null;
           if (latest.sender_id != myId && typeof NotifManager !== 'undefined') {
             NotifManager.send(`New message from ${latest.sender_name}`, {
               body: latest.content || (latest.type === 'image' ? 'Sent an image' : 'Sent a file'),
               tag: `chat-${this.currentConvId}`
             });
           }
         }

         this.messages   = newMessages; 
         this.renderMessages(prevCount !== data.messages.length); 
       } 
     } catch (e) { console.error('Chat history error', e); } 
   }, 

   renderMessages(forceScroll = true) { 
     const list       = this.messageList; 
     const atBottom   = list.scrollHeight - list.scrollTop <= list.clientHeight + 80; 
     const myId       = typeof currentUser !== 'undefined' ? currentUser.id : null; 
     
     let html = ''; 
     let prevSenderId = null; 
     let prevTime     = null; 
     this.messages.forEach((m, idx) => { 
       const isSent       = m.sender_id == myId; 
       const isAI         = m.sender_id === 'ai_system';
       const mTime        = new Date(m.created_at);
       const isGrouped    = prevSenderId === m.sender_id 
                         && prevTime 
                         && (mTime - prevTime) < 3 * 60 * 1000; 
       
       prevSenderId = m.sender_id; 
       prevTime     = mTime; 
       const timeStr = mTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); 
       
       html += ` 
         <div class="msg-row ${isSent ? 'sent' : 'received'} ${isGrouped ? 'grouped' : ''} ${isAI ? 'ai-msg' : ''}" 
              id="msg-${m.id}"> 
           ${(!isSent && !isGrouped) || isAI ? `
             <div class="msg-avatar" style="${isAI ? 'background: linear-gradient(135deg, #7c6bff, #b06bff);' : ''}">
               ${isAI ? 'AI' : initials(m.sender_name || '?')}
             </div>` : `<div class="msg-avatar-spacer"></div>`} 
           <div class="msg-bubble-wrap"> 
             ${(!isSent && !isGrouped) || isAI ? `<span class="msg-sender" style="${isAI ? 'color: #b06bff;' : ''}">${escapeHtml(m.sender_name || '')}</span>` : ''} 
             ${m.reply_to_id ? `
               <div class="msg-reply-preview">
                 <span>↩ ${m.reply_type === 'text' ? escapeHtml(m.reply_content || 'Message') : (m.reply_type === 'image' ? 'Image' : 'File')}</span>
               </div>` : ''} 
             <div class="msg-bubble" style="${isAI ? 'border-color: rgba(124, 107, 255, 0.3); background: rgba(124, 107, 255, 0.05);' : ''}"> 
               ${this._renderContent(m)} 
               <div class="msg-meta-row"> 
                 <span class="msg-time">${timeStr}</span> 
               </div> 
             </div> 
             ${!isAI ? `
             <div class="msg-actions"> 
               <button onclick="Chat.replyTo(${m.id}, \`${escapeHtml(m.content || '').replace(/`/g, '\\`')}\`)" title="Reply">↩</button> 
               <button onclick="Chat.deleteMessage(${m.id})" title="Delete for me">🗑</button> 
             </div>` : ''}
           </div> 
         </div>`; 
     }); 
     list.innerHTML = html || `<div style="text-align:center; padding:3rem 1rem; color:var(--muted);"><p>No messages yet.</p></div>`; 
     if (forceScroll || atBottom) list.scrollTop = list.scrollHeight; 
   }, 

   _renderContent(m) { 
     if (m.type === 'image') return `<img src="${m.file_url}" class="msg-image" onclick="Chat.lightbox('${m.file_url}')">`; 
     if (m.type === 'pdf' || m.type === 'file') { 
       return `<a href="${m.file_url}" target="_blank" class="msg-file-link">📎 ${escapeHtml(m.file_name || 'File')}</a>`; 
     } 
     return `<div class="msg-text">${this._linkify(escapeHtml(m.content || ''))}</div>`; 
   }, 

   _linkify(text) { 
     return text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color:var(--teal);">$1</a>'); 
   }, 

   lightbox(url) { 
     const el = document.createElement('div'); 
     el.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;display:grid;place-items:center;cursor:zoom-out;`; 
     el.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:90vh;border-radius:8px;">`; 
     el.onclick = () => el.remove(); 
     document.body.appendChild(el); 
   }, 

   replyTo(msgId, content) { 
     this.replyToId      = msgId; 
     this.replyToContent = content; 
     const bar = document.getElementById('replyBar'); 
     if (bar) { 
       bar.style.display = 'flex'; 
       const preview = content ? (content.length > 50 ? content.slice(0, 50) + '...' : content) : 'Media';
       document.getElementById('replyBarText').textContent = preview; 
     } 
     this.chatInput.focus(); 
   }, 

   clearReply() { 
     this.replyToId = null; 
     this.replyToContent = null;
     const bar = document.getElementById('replyBar'); 
     if (bar) bar.style.display = 'none'; 
   }, 

   async deleteMessage(msgId) {
     if (!confirm("Hide this message for you?")) return;
     
     // Optimistic local hide
     const row = document.getElementById(`msg-${msgId}`);
     if (row) row.style.display = 'none';

     try {
       const res = await fetch(`${API_BASE}?action=chat_delete_msg`, {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({ message_id: msgId, csrf: window.PORTAL_CSRF_TOKEN })
       });
       const data = await res.json();
       if (!data.ok) {
         if (row) row.style.display = 'flex'; // Restore if failed
         showToast("Failed to delete", "error");
       } else {
         // Filter it out from local array and re-render
         this.messages = this.messages.filter(m => m.id != msgId);
         this.renderMessages(false);
       }
     } catch (e) {
       if (row) row.style.display = 'flex';
       showToast("Connection error", "error");
     }
   },

   async sendMessage(isFile = false) { 
     const content = this.chatInput.value.trim(); 
     if (!this.currentConvId || (!content && !isFile)) return; 

     if (this.currentConvId === 'ai') {
       this.chatInput.value = '';
       this.chatInput.style.height = 'auto';
       return this.sendAIMessage(content);
     }

     const formData = new FormData(); 
     formData.append('conversation_id', this.currentConvId); 
     formData.append('content', content); 
     formData.append('csrf', window.PORTAL_CSRF_TOKEN); 
     if (this.replyToId) formData.append('reply_to_id', this.replyToId); 
     if (isFile && this.chatFileInput.files[0]) formData.append('file', this.chatFileInput.files[0]); 
     
     this.chatInput.value = ''; 
     this.chatInput.style.height = 'auto'; 
     this.clearReply(); 
     
     try { 
       const res  = await fetch(`${API_BASE}?action=chat_send`, { method: 'POST', body: formData }); 
       const data = await res.json(); 
       if (data.ok) { 
         this.messages.push(data.message); 
         this.renderMessages(true); 
         
         // Emit to socket
         if (this.socket) {
           this.socket.emit("send_msg", data.message);
         }
       } 
     } catch (e) { showToast('Failed to send', 'error'); } 
   }, 

   filterChats(query) {
     const q = query.toLowerCase();
     const items = this.chatListContainer.querySelectorAll('.chat-list-item');
     items.forEach(item => {
       const title = item.querySelector('h4').textContent.toLowerCase();
       item.style.display = title.includes(q) ? 'flex' : 'none';
     });
   },

   startPolling() { 
     this.stopPolling(); 
     this.pollInterval = setInterval(() => { this.loadMessages(); this.loadChatList(); }, 3000); 
   }, 

   stopPolling() { 
     if (this.pollInterval) clearInterval(this.pollInterval); 
   }, 

   _relativeTime(dateStr) { 
     const diff = Date.now() - new Date(dateStr).getTime(); 
     const mins  = Math.floor(diff / 60000); 
     if (mins < 1)  return 'now'; 
     if (mins < 60) return `${mins}m`; 
     const hrs = Math.floor(mins / 60); 
     if (hrs < 24)  return `${hrs}h`; 
     return `${Math.floor(hrs / 24)}d`; 
   } 
 }; 

 window.startDirectChat = async (userId) => { 
   try { 
     const res  = await fetch(`${API_BASE}?action=chat_start_direct`, { 
       method:  'POST', 
       headers: { 'Content-Type': 'application/json' }, 
       body:    JSON.stringify({ user_id: userId, csrf: window.PORTAL_CSRF_TOKEN }) 
     }); 
     const data = await res.json(); 
     if (data.ok) { 
       if (!Chat.drawer.classList.contains('open')) Chat.drawer.classList.add('open'); 
       await Chat.loadChatList(); 
       Chat.openChat(data.conversation_id); 
     } 
   } catch (e) { showToast('Failed to start chat', 'error'); } 
 }; 

 function initials(name) { 
   if (!name) return '?'; 
   return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2); 
 } 

 function escapeHtml(str) { 
   if (!str) return ''; 
   return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])); 
 } 

 if (document.readyState === 'complete') Chat.init(); 
 else window.addEventListener('load', () => Chat.init());
