/**
 * Real-time WebSocket Server for Campus Portal Chat
 * Powered by Socket.io
 * 
 * Instructions:
 * 1. Install Node.js
 * 2. Run: npm install socket.io
 * 3. Run: node chat_server.js
 */

const { Server } = require("socket.io");
const http = require("http");

// Create HTTP server to handle both Socket.io and PHP notifications
const server = http.createServer((req, res) => {
  // Allow PHP to trigger notifications via local POST request
  if (req.method === 'POST' && req.url === '/notify') {
    let body = '';
    req.on('data', chunk => body += chunk.toString());
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        // data: { user_id, title, message, type }
        io.emit("global_notification", data);
        res.writeHead(200);
        res.end('ok');
      } catch (e) {
        res.writeHead(400);
        res.end('error');
      }
    });
  } else {
    res.writeHead(404);
    res.end();
  }
});

const io = new Server(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

io.on("connection", (socket) => {
  console.log("User connected:", socket.id);

  // Join a specific conversation room
  socket.on("join_chat", (convId) => {
    // Leave previous rooms if any
    socket.rooms.forEach(room => {
      if (room.startsWith("conv_")) socket.leave(room);
    });
    socket.join(`conv_${convId}`);
    console.log(`Socket ${socket.id} joined conversation ${convId}`);
  });

  // Handle new message (from client)
  socket.on("send_msg", (data) => {
    // Broadcast to everyone in the room except sender
    socket.to(`conv_${data.conversation_id}`).emit("new_msg", data);
    
    // Also trigger global notification for real-time alerts if not in chat
    io.emit("global_notification", {
      user_id: data.target_id, // Might be null for groups
      title: `New Message from ${data.sender_name}`,
      message: data.content.substring(0, 50),
      tag: `chat-${data.conversation_id}`
    });
  });

  // Typing indicators
  socket.on("typing", (data) => {
    socket.to(`conv_${data.convId}`).emit("user_typing", data);
  });

  socket.on("disconnect", () => {
    console.log("User disconnected:", socket.id);
  });
});

server.listen(3000, () => {
  console.log("WebSocket Server running on port 3000...");
});
