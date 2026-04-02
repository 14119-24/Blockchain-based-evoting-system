// Main JavaScript file for Blockchain Voting System

class App {
    constructor() {
        this.apiBase = '/api';
        this.isAdmin = false;
        this.currentUser = null;
        this.currentElection = null;
        
        this.initialize();
    }
    
    async initialize() {
        // Check authentication status
        await this.checkAuth();
        
        // Initialize event listeners
        this.initEventListeners();
        
        // Load initial data based on page
        this.loadPageData();
    }
    
    // Check authentication status
    async checkAuth() {
        try {
            const response = await fetch(`${this.apiBase}/auth.php?action=check_session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.currentUser = data.data;
                    this.isAdmin = data.data.user_type === 'admin';
                    
                    // Update UI based on auth status
                    this.updateAuthUI();
                    
                    return true;
                }
            }
            
            // Not authenticated
            this.currentUser = null;
            this.isAdmin = false;
            this.updateAuthUI();
            return false;
            
        } catch (error) {
            console.error('Auth check error:', error);
            return false;
        }
    }
    
    // Update UI based on authentication status
    updateAuthUI() {
        const authElements = document.querySelectorAll('[data-auth]');
        const adminElements = document.querySelectorAll('[data-admin]');
        
        authElements.forEach(element => {
            const shouldShow = this.currentUser ? 
                element.dataset.auth === 'authenticated' : 
                element.dataset.auth === 'unauthenticated';
            
            element.style.display = shouldShow ? '' : 'none';
        });
        
        adminElements.forEach(element => {
            element.style.display = this.isAdmin ? '' : 'none';
        });
        
        // Update user info if available
        if (this.currentUser) {
            const userInfoElements = document.querySelectorAll('[data-user-info]');
            userInfoElements.forEach(element => {
                const infoType = element.dataset.userInfo;
                if (this.currentUser[infoType]) {
                    element.textContent = this.currentUser[infoType];
                }
            });
        }
    }
    
    // Initialize event listeners
    initEventListeners() {
        // Logout button
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="logout"]')) {
                this.logout();
            }
            
            // Close modal on outside click
            if (e.target.classList.contains('modal')) {
                this.hideModal();
            }
            
            // Close modal on escape key
            if (e.key === 'Escape') {
                this.hideModal();
            }
        });
        
        // Form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.dataset.action) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });
    }
    
    // Handle form submissions
    async handleFormSubmit(form) {
        const action = form.dataset.action;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
        if (csrfToken) {
            data.csrf_token = csrfToken;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        try {
            let endpoint, apiAction;
            
            switch (action) {
                case 'vote':
                    endpoint = 'vote.php';
                    apiAction = 'submit';
                    break;
                case 'create_election':
                    endpoint = 'admin.php';
                    apiAction = 'create_election';
                    break;
                case 'add_candidate':
                    endpoint = 'admin.php';
                    apiAction = 'add_candidate';
                    break;
                // Add more cases as needed
                default:
                    throw new Error('Unknown form action');
            }
            
            const response = await fetch(`${this.apiBase}/${endpoint}?action=${apiAction}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('success', result.data.message || 'Operation successful');
                
                // Reset form if needed
                if (form.dataset.reset === 'true') {
                    form.reset();
                }
                
                // Close modal if form is in modal
                const modal = form.closest('.modal');
                if (modal) {
                    this.hideModal();
                }
                
                // Refresh data if needed
                if (form.dataset.refresh === 'true') {
                    this.loadPageData();
                }
                
            } else {
                this.showNotification('error', result.error || 'Operation failed');
                
                // Show field errors if available
                if (result.errors) {
                    this.showFormErrors(form, result.errors);
                }
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            this.showNotification('error', 'Network error. Please try again.');
            
        } finally {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    // Show form field errors
    showFormErrors(form, errors) {
        // Clear previous errors
        form.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
        });
        
        // Show new errors
        Object.entries(errors).forEach(([field, message]) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                const errorEl = input.nextElementSibling?.classList.contains('error-message') ? 
                    input.nextElementSibling : 
                    input.parentElement.querySelector('.error-message');
                
                if (errorEl) {
                    errorEl.textContent = message;
                }
            }
        });
    }
    
    // Load page-specific data
    loadPageData() {
        const path = window.location.pathname;
        const page = path.split('/').pop();
        
        switch (page) {
            case 'vote.html':
                this.loadVotingPage();
                break;
            case 'admin.html':
                this.loadAdminPage();
                break;
            case 'index.html':
                this.loadHomePage();
                break;
            // Add more pages as needed
        }
    }
    
    // Load voting page data
    async loadVotingPage() {
        if (!this.currentUser || this.isAdmin) {
            window.location.href = 'login.html';
            return;
        }
        
        // Check if user has already voted
        if (this.currentUser.has_voted) {
            this.showAlreadyVoted();
            return;
        }
        
        // Load current election
        const electionId = new URLSearchParams(window.location.search).get('election') || 1;
        await this.loadElection(electionId);
        
        // Load candidates
        await this.loadCandidates(electionId);
    }
    
    // Load admin dashboard data
    async loadAdminPage() {
        if (!this.isAdmin) {
            window.location.href = 'login.html';
            return;
        }
        
        // Load admin statistics
        await this.loadAdminStats();
        
        // Load recent elections
        await this.loadRecentElections();
        
        // Load pending voters
        await this.loadPendingVoters();
    }
    
    // Load home page data
    async loadHomePage() {
        // Load statistics
        await this.loadStatistics();
        
        // Load active elections
        await this.loadActiveElections();
        
        // Load recent blocks
        await this.loadRecentBlocks();
    }
    
    // API methods
    async loadElection(electionId) {
        try {
            const response = await fetch(`${this.apiBase}/admin.php?action=get_elections`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status: 'ongoing' })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.elections.length > 0) {
                    const election = data.data.elections.find(e => e.election_id == electionId);
                    if (election) {
                        this.currentElection = election;
                        this.updateElectionUI(election);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading election:', error);
        }
    }
    
    async loadCandidates(electionId) {
        try {
            // This would be a separate API endpoint in a real implementation
            const response = await fetch(`${this.apiBase}/candidates.php?election_id=${electionId}`);
            if (response.ok) {
                const candidates = await response.json();
                this.updateCandidatesUI(candidates);
            }
        } catch (error) {
            console.error('Error loading candidates:', error);
            
            // Fallback for demo
            const demoCandidates = [
                { candidate_id: 1, candidate_name: 'John Smith', party: 'Progress Party', symbol: '🌲' },
                { candidate_id: 2, candidate_name: 'Jane Doe', party: 'Unity Party', symbol: '🤝' },
                { candidate_id: 3, candidate_name: 'Bob Johnson', party: 'Future Party', symbol: '🚀' }
            ];
            this.updateCandidatesUI(demoCandidates);
        }
    }
    
    async loadAdminStats() {
        try {
            const response = await fetch(`${this.apiBase}/admin.php?action=get_blockchain_stats`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateAdminStatsUI(data.data.blockchain_stats);
                }
            }
        } catch (error) {
            console.error('Error loading admin stats:', error);
        }
    }
    
    async loadStatistics() {
        try {
            const response = await fetch(`${this.apiBase}/admin.php?action=get_blockchain_stats`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateStatisticsUI(data.data.blockchain_stats);
                }
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }
    
    async loadRecentBlocks() {
        try {
            const response = await fetch(`${this.apiBase}/blocks.php`);
            if (response.ok) {
                const blocks = await response.json();
                this.updateRecentBlocksUI(blocks);
            }
        } catch (error) {
            console.error('Error loading recent blocks:', error);
        }
    }
    
    // UI update methods
    updateElectionUI(election) {
        document.querySelectorAll('[data-election-name]').forEach(el => {
            el.textContent = election.election_name;
        });
        
        document.querySelectorAll('[data-election-description]').forEach(el => {
            el.textContent = election.description || '';
        });
        
        // Update progress
        const now = new Date();
        const start = new Date(election.start_date);
        const end = new Date(election.end_date);
        const total = end - start;
        const elapsed = now - start;
        const progress = Math.min(Math.max((elapsed / total) * 100, 0), 100);
        
        const progressBar = document.querySelector('.progress');
        const progressLabel = document.querySelector('.progress-label');
        
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
        
        if (progressLabel) {
            const daysLeft = Math.ceil((end - now) / (1000 * 60 * 60 * 24));
            progressLabel.innerHTML = `
                <span>${start.toLocaleDateString()}</span>
                <span>${daysLeft > 0 ? `${daysLeft} days left` : 'Ending soon'}</span>
                <span>${end.toLocaleDateString()}</span>
            `;
        }
    }
    
    updateCandidatesUI(candidates) {
        const container = document.getElementById('candidates-container');
        if (!container) return;
        
        container.innerHTML = candidates.map(candidate => `
            <div class="candidate-card" data-candidate-id="${candidate.candidate_id}" 
                 onclick="app.selectCandidate(${candidate.candidate_id})">
                <div class="candidate-image">
                    ${candidate.symbol || '👤'}
                </div>
                <div class="candidate-info">
                    <h3>${candidate.candidate_name}</h3>
                    <p>${candidate.party || 'Independent'}</p>
                </div>
                <button class="btn btn-primary vote-btn" 
                        onclick="app.submitVote(${candidate.candidate_id})">
                    <i class="fas fa-vote-yea"></i> Vote
                </button>
            </div>
        `).join('');
    }
    
    updateAdminStatsUI(stats) {
        // Update stat cards
        document.querySelectorAll('[data-stat="total_blocks"]').forEach(el => {
            el.textContent = stats.total_blocks;
        });
        
        document.querySelectorAll('[data-stat="total_transactions"]').forEach(el => {
            el.textContent = stats.total_transactions;
        });
        
        // Update latest block info
        if (stats.latest_block) {
            const latestBlockEl = document.querySelector('[data-latest-block]');
            if (latestBlockEl) {
                latestBlockEl.innerHTML = `
                    <div>Block #${stats.latest_block.block_id}</div>
                    <small>${new Date(stats.latest_block.timestamp).toLocaleString()}</small>
                `;
            }
        }
    }
    
    updateStatisticsUI(stats) {
        document.getElementById('total-blocks')?.textContent = stats.total_blocks;
        document.getElementById('total-votes')?.textContent = stats.total_transactions;
    }
    
    updateRecentBlocksUI(blocks) {
        const container = document.getElementById('blocks-list');
        if (!container) return;
        
        const recentBlocks = blocks.slice(-5).reverse();
        container.innerHTML = recentBlocks.map(block => `
            <div class="block-item">
                <div class="block-header-info">
                    <span class="block-number">Block #${block.block_id}</span>
                    <span class="block-time">${new Date(block.timestamp).toLocaleTimeString()}</span>
                </div>
                <div class="block-hash">
                    <span class="hash-label">Hash:</span>
                    <code class="hash-value">${block.block_hash.substring(0, 20)}...</code>
                </div>
                <div class="block-transactions">
                    <i class="fas fa-exchange-alt"></i>
                    <span>${block.transactions_count} transactions</span>
                </div>
            </div>
        `).join('');
    }
    
    // Voting methods
    selectCandidate(candidateId) {
        // Deselect all candidates
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select clicked candidate
        const selectedCard = document.querySelector(`[data-candidate-id="${candidateId}"]`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
            this.selectedCandidate = candidateId;
        }
    }
    
    async submitVote(candidateId) {
        if (!this.currentUser || !this.currentElection) {
            this.showNotification('error', 'Please login first');
            return;
        }
        
        if (this.currentUser.has_voted) {
            this.showNotification('error', 'You have already voted');
            return;
        }
        
        if (!candidateId) {
            this.showNotification('error', 'Please select a candidate');
            return;
        }
        
        // Get voter's private key from session storage
        const privateKey = sessionStorage.getItem('private_key');
        if (!privateKey) {
            this.showNotification('error', 'Missing cryptographic keys');
            return;
        }
        
        // Create vote data
        const voteData = {
            voter_id: this.currentUser.voter_id,
            election_id: this.currentElection.election_id,
            candidate_id: candidateId,
            timestamp: Date.now()
        };
        
        try {
            // Create digital signature
            const signature = await this.createSignature(JSON.stringify(voteData), privateKey);
            
            // Submit vote
            const response = await fetch(`${this.apiBase}/vote.php?action=submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    election_id: this.currentElection.election_id,
                    candidate_id: candidateId,
                    signature: signature
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('success', 'Vote successfully cast!');
                
                // Store receipt
                sessionStorage.setItem('last_vote_receipt', JSON.stringify(result.data));
                
                // Update UI
                this.currentUser.has_voted = true;
                this.updateAuthUI();
                
                // Show success screen
                this.showVoteSuccess(result.data);
                
            } else {
                this.showNotification('error', result.error || 'Failed to cast vote');
            }
            
        } catch (error) {
            console.error('Vote submission error:', error);
            this.showNotification('error', 'Network error. Please try again.');
        }
    }
    
    async createSignature(data, privateKey) {
        // In a real implementation, use proper cryptographic signing
        // This is a simplified version for demo
        return btoa(`${data}|SIGNED|${Date.now()}`);
    }
    
    // Modal methods
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
    
    hideModal() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
    
    // Notification methods
    showNotification(type, message) {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) {
            existing.remove();
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            background-color: ${type === 'success' ? '#dcfce7' : '#fee2e2'};
            color: ${type === 'success' ? '#166534' : '#991b1b'};
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 3000;
            animation: slideIn 0.3s ease-out;
        `;
        
        // Add animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    // Other methods
    showAlreadyVoted() {
        const container = document.querySelector('.voting-container');
        if (container) {
            container.innerHTML = `
                <div class="already-voted">
                    <div class="already-voted-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>You Have Already Voted</h2>
                    <p>Thank you for participating in this election. Your vote has been recorded on the blockchain.</p>
                    <div class="already-voted-actions">
                        <a href="index.html" class="btn btn-primary">
                            <i class="fas fa-home"></i> Return Home
                        </a>
                        <button onclick="app.verifyLastVote()" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Verify My Vote
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    showVoteSuccess(receipt) {
        const container = document.querySelector('.voting-container');
        if (container) {
            container.innerHTML = `
                <div class="vote-success">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Vote Successfully Cast!</h2>
                    <p>Your vote has been securely recorded on the blockchain.</p>
                    
                    <div class="receipt-details">
                        <div class="receipt-item">
                            <span class="receipt-label">Transaction ID:</span>
                            <code class="receipt-value">${receipt.transaction_id}</code>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Block Hash:</span>
                            <code class="receipt-value">${receipt.block_hash.substring(0, 32)}...</code>
                        </div>
                        <div class="receipt-item">
                            <span class="receipt-label">Timestamp:</span>
                            <span class="receipt-value">${receipt.timestamp}</span>
                        </div>
                    </div>
                    
                    <div class="success-actions">
                        <a href="index.html" class="btn btn-primary">
                            <i class="fas fa-home"></i> Return Home
                        </a>
                        <button onclick="app.verifyVote('${receipt.transaction_id}')" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Verify on Blockchain
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    async verifyVote(transactionId) {
        try {
            const response = await fetch(`${this.apiBase}/vote.php?action=verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ transaction_id: transactionId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showModal('verification-modal');
                document.getElementById('verification-details').innerHTML = `
                    <h3>Verification Result</h3>
                    <div class="verification-status success">
                        <i class="fas fa-check-circle"></i>
                        Vote Verified on Blockchain
                    </div>
                    <div class="verification-details">
                        <p><strong>Transaction ID:</strong> ${result.data.transaction.transaction_id}</p>
                        <p><strong>Block Hash:</strong> ${result.data.transaction.block_hash}</p>
                        <p><strong>Recorded:</strong> ${new Date(result.data.transaction.timestamp).toLocaleString()}</p>
                    </div>
                `;
            } else {
                this.showNotification('error', 'Verification failed');
            }
        } catch (error) {
            console.error('Verification error:', error);
            this.showNotification('error', 'Network error during verification');
        }
    }
    
    async verifyLastVote() {
        const receipt = sessionStorage.getItem('last_vote_receipt');
        if (receipt) {
            const receiptData = JSON.parse(receipt);
            await this.verifyVote(receiptData.transaction_id);
        } else {
            this.showNotification('error', 'No vote receipt found');
        }
    }
    
    async logout() {
        try {
            const response = await fetch(`${this.apiBase}/auth.php?action=logout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Clear storage
                sessionStorage.clear();
                localStorage.removeItem('remember_voter');
                
                // Redirect to home page
                window.location.href = 'index.html';
            }
        } catch (error) {
            console.error('Logout error:', error);
            // Still redirect even if logout fails
            window.location.href = 'index.html';
        }
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new App();
});

// Utility functions available globally
window.refreshBlockchain = async function() {
    if (window.app) {
        await window.app.loadRecentBlocks();
        window.app.showNotification('success', 'Blockchain data refreshed');
    }
};

window.togglePassword = function(inputId) {
    const input = document.getElementById(inputId);
    const toggleBtn = input.nextElementSibling?.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        toggleBtn?.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        toggleBtn?.classList.replace('fa-eye-slash', 'fa-eye');
    }
};

// Format date utility
window.formatDate = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Copy to clipboard utility
window.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text)
        .then(() => {
            // Show success message
            const notification = document.createElement('div');
            notification.textContent = 'Copied to clipboard!';
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--primary-color);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 0.25rem;
                z-index: 1000;
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 2000);
        })
        .catch(err => {
            console.error('Failed to copy:', err);
        });
};