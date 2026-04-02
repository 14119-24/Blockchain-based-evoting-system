const express = require('express');
const cors = require('cors');
const path = require('path');
const db = require('./config/db');
require('dotenv').config();

const app = express();

// Middleware
app.use(cors({
    origin: '*', // In production, specify your domain
    methods: ['GET', 'POST', 'PUT', 'DELETE'],
    credentials: true
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static files from public directory
app.use(express.static(path.join(__dirname, 'public')));

// Database connection check
app.get('/api/health', (req, res) => {
    res.json({ 
        status: 'OK', 
        message: 'Voting system is running',
        timestamp: new Date().toISOString()
    });
});

// User Registration API (FIXED)
app.post('/api/register', async (req, res) => {
    try {
        console.log('Registration attempt:', req.body);
        
        const { username, email, password, role = 'voter' } = req.body;
        
        // Validation
        if (!username || !email || !password) {
            return res.status(400).json({ 
                success: false,
                error: 'All fields are required' 
            });
        }
        
        // Check if user exists
        const existingUser = await db.query(
            'SELECT id FROM users WHERE email = ? OR username = ?',
            [email, username]
        );
        
        if (existingUser.length > 0) {
            return res.status(409).json({ 
                success: false,
                error: 'User already exists with this email or username' 
            });
        }
        
        // In production: Hash password using bcrypt
        // For now, store as-is (NOT SECURE - for demo only)
        const hashedPassword = password; // Replace with: await bcrypt.hash(password, 10);
        
        // Insert new user
        const result = await db.query(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
            [username, email, hashedPassword, role]
        );
        
        console.log('User registered:', result.insertId);
        
        res.status(201).json({ 
            success: true,
            message: 'User registered successfully',
            userId: result.insertId 
        });
        
    } catch (error) {
        console.error('Registration error:', error);
        res.status(500).json({ 
            success: false,
            error: 'Server error during registration',
            details: process.env.NODE_ENV === 'development' ? error.message : undefined
        });
    }
});

// User Login API
app.post('/api/login', async (req, res) => {
    try {
        const { email, password } = req.body;
        
        // Find user
        const [users] = await db.query(
            'SELECT id, username, email, password, role FROM users WHERE email = ?',
            [email]
        );
        
        if (users.length === 0) {
            return res.status(401).json({ 
                success: false,
                error: 'Invalid credentials' 
            });
        }
        
        const user = users[0];
        
        // In production: Verify password with bcrypt.compare()
        // For now, simple comparison (NOT SECURE - for demo only)
        if (password !== user.password) {
            return res.status(401).json({ 
                success: false,
                error: 'Invalid credentials' 
            });
        }
        
        // Generate session/token (simple for demo)
        const sessionToken = require('crypto').randomBytes(32).toString('hex');
        
        await db.query(
            'INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))',
            [user.id, sessionToken]
        );
        
        res.json({
            success: true,
            message: 'Login successful',
            user: {
                id: user.id,
                username: user.username,
                email: user.email,
                role: user.role
            },
            token: sessionToken
        });
        
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ 
            success: false,
            error: 'Server error during login' 
        });
    }
});

// Serve main HTML files
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.get('/register', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'register.html'));
});

app.get('/login', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'login.html'));
});

// API Routes
app.get('/api/users', async (req, res) => {
    try {
        const users = await db.query('SELECT id, username, email, role, created_at FROM users WHERE is_active = TRUE');
        res.json({ success: true, data: users });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Start server
const PORT = process.env.PORT || 3000;
const server = app.listen(PORT, () => {
    console.log(`🚀 Server running on: http://localhost:${PORT}`);
    console.log(`📁 Static files served from: ${path.join(__dirname, 'public')}`);
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('Shutting down server...');
    await db.close();
    server.close(() => {
        console.log('Server closed');
        process.exit(0);
    });
});