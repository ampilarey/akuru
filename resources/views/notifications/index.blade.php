@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Notifications</h1>
                    <p class="mt-1 text-sm text-gray-600">Manage your notifications and preferences</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button id="markAllRead" class="btn-secondary">
                        Mark All as Read
                    </button>
                    <button id="refreshNotifications" class="btn-primary">
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filter Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button class="notification-filter border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm active" data-filter="all">
                        All Notifications
                    </button>
                    <button class="notification-filter border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-filter="unread">
                        Unread
                    </button>
                    <button class="notification-filter border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-filter="email">
                        Email
                    </button>
                    <button class="notification-filter border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-filter="sms">
                        SMS
                    </button>
                    <button class="notification-filter border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm" data-filter="in_app">
                        In-App
                    </button>
                </nav>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="bg-white shadow-sm rounded-lg">
            <div id="notificationsList" class="divide-y divide-gray-200">
                <!-- Notifications will be loaded here via AJAX -->
                <div class="p-8 text-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brandMaroon-600 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading notifications...</p>
                </div>
            </div>
        </div>

        <!-- Load More Button -->
        <div class="mt-6 text-center">
            <button id="loadMore" class="btn-secondary" style="display: none;">
                Load More Notifications
            </button>
        </div>
    </div>
</div>

<!-- Notification Detail Modal -->
<div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Notification Details</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mt-2">
                <p class="text-sm text-gray-500" id="modalDate"></p>
                <p class="text-sm text-gray-500" id="modalType"></p>
            </div>
            <div class="mt-4">
                <p class="text-gray-700" id="modalMessage"></p>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="markAsReadModal" class="btn-secondary">Mark as Read</button>
                <button id="closeModalBtn" class="btn-primary">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let currentFilter = 'all';
    let isLoading = false;

    // Load notifications
    function loadNotifications(page = 1, filter = 'all', append = false) {
        if (isLoading) return;
        
        isLoading = true;
        const container = document.getElementById('notificationsList');
        
        if (page === 1 && !append) {
            container.innerHTML = '<div class="p-8 text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brandMaroon-600 mx-auto"></div><p class="mt-2 text-gray-500">Loading notifications...</p></div>';
        }

        fetch(`/api/notifications?page=${page}&filter=${filter}&limit=20`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (append) {
                        container.innerHTML += renderNotifications(data.data);
                    } else {
                        container.innerHTML = renderNotifications(data.data);
                    }
                    
                    // Show/hide load more button
                    const loadMoreBtn = document.getElementById('loadMore');
                    if (data.data.length === 20) {
                        loadMoreBtn.style.display = 'block';
                    } else {
                        loadMoreBtn.style.display = 'none';
                    }
                } else {
                    container.innerHTML = '<div class="p-8 text-center text-gray-500">Failed to load notifications</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="p-8 text-center text-gray-500">Error loading notifications</div>';
            })
            .finally(() => {
                isLoading = false;
            });
    }

    // Render notifications
    function renderNotifications(notifications) {
        if (notifications.length === 0) {
            return '<div class="p-8 text-center text-gray-500">No notifications found</div>';
        }

        return notifications.map(notification => `
            <div class="p-6 hover:bg-gray-50 cursor-pointer notification-item ${notification.read_at ? '' : 'bg-blue-50 border-l-4 border-blue-400'}" 
                 data-id="${notification.id}" 
                 data-title="${notification.title}" 
                 data-message="${notification.message}" 
                 data-date="${notification.created_at}" 
                 data-type="${notification.type}"
                 data-read="${notification.read_at ? 'true' : 'false'}">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-2 h-2 ${notification.read_at ? 'bg-gray-400' : 'bg-blue-600'} rounded-full mt-2"></div>
                    </div>
                    <div class="ml-3 flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-900">${notification.title}</h3>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTypeColor(notification.type)}">
                                    ${notification.type.toUpperCase()}
                                </span>
                                <span class="text-xs text-gray-500">${formatDate(notification.created_at)}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">${notification.message}</p>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Get type color
    function getTypeColor(type) {
        const colors = {
            'email': 'bg-blue-100 text-blue-800',
            'sms': 'bg-green-100 text-green-800',
            'push': 'bg-purple-100 text-purple-800',
            'in_app': 'bg-yellow-100 text-yellow-800'
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    }

    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    }

    // Filter buttons
    document.querySelectorAll('.notification-filter').forEach(button => {
        button.addEventListener('click', function() {
            // Update active state
            document.querySelectorAll('.notification-filter').forEach(btn => btn.classList.remove('active', 'border-brandMaroon-500', 'text-brandMaroon-600'));
            this.classList.add('active', 'border-brandMaroon-500', 'text-brandMaroon-600');
            
            // Load notifications with new filter
            currentFilter = this.dataset.filter;
            currentPage = 1;
            loadNotifications(1, currentFilter);
        });
    });

    // Load more button
    document.getElementById('loadMore').addEventListener('click', function() {
        currentPage++;
        loadNotifications(currentPage, currentFilter, true);
    });

    // Mark all as read
    document.getElementById('markAllRead').addEventListener('click', function() {
        fetch('/api/notifications/mark-all-read', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(1, currentFilter);
                }
            });
    });

    // Refresh notifications
    document.getElementById('refreshNotifications').addEventListener('click', function() {
        loadNotifications(1, currentFilter);
    });

    // Notification click handler
    document.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem) {
            const id = notificationItem.dataset.id;
            const title = notificationItem.dataset.title;
            const message = notificationItem.dataset.message;
            const date = notificationItem.dataset.date;
            const type = notificationItem.dataset.type;
            const isRead = notificationItem.dataset.read === 'true';

            // Show modal
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('modalDate').textContent = formatDate(date);
            document.getElementById('modalType').textContent = type.toUpperCase();
            document.getElementById('notificationModal').classList.remove('hidden');

            // Mark as read if not already read
            if (!isRead) {
                markAsRead(id);
            }
        }
    });

    // Mark as read function
    function markAsRead(id) {
        fetch(`/api/notifications/${id}/mark-read`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const notificationItem = document.querySelector(`[data-id="${id}"]`);
                    if (notificationItem) {
                        notificationItem.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-400');
                        notificationItem.querySelector('.w-2.h-2').classList.remove('bg-blue-600');
                        notificationItem.querySelector('.w-2.h-2').classList.add('bg-gray-400');
                        notificationItem.dataset.read = 'true';
                    }
                }
            });
    }

    // Close modal
    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('notificationModal').classList.add('hidden');
    });

    document.getElementById('closeModalBtn').addEventListener('click', function() {
        document.getElementById('notificationModal').classList.add('hidden');
    });

    // Mark as read from modal
    document.getElementById('markAsReadModal').addEventListener('click', function() {
        const notificationItem = document.querySelector('.notification-item:hover');
        if (notificationItem) {
            const id = notificationItem.dataset.id;
            markAsRead(id);
        }
        document.getElementById('notificationModal').classList.add('hidden');
    });

    // Load initial notifications
    loadNotifications();
});
</script>
@endpush
