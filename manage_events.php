<?php
// Include config first (which will handle session configuration and start)
require_once 'config.php';

// Check if user is logged in and has appropriate role
requireAnyRole(['club_manager', 'admin']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get clubs managed by current user (or all clubs for admin)
function getManagedClubs($conn, $user_id, $user_role) {
    if ($user_role === 'admin') {
        $sql = "SELECT c.*, u.username as manager_name
                FROM clubs c
                LEFT JOIN club_managers cm ON c.id = cm.club_id AND cm.status = 'active'
                LEFT JOIN users u ON cm.user_id = u.id
                ORDER BY c.name";
        $result = $conn->query($sql);
    } else {
        $sql = "SELECT c.*, u.username as manager_name
                FROM clubs c
                JOIN club_managers cm ON c.id = cm.club_id
                LEFT JOIN users u ON cm.user_id = u.id
                WHERE cm.user_id = ? AND cm.status = 'active'
                ORDER BY c.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get events for managed clubs
function getClubEvents($conn, $club_ids) {
    if (empty($club_ids)) return [];
    
    $placeholders = str_repeat('?,', count($club_ids) - 1) . '?';
    $sql = "SELECT e.*, c.name as club_name, c.initials as club_initials
            FROM events e
            JOIN clubs c ON e.club_id = c.id
            WHERE e.club_id IN ($placeholders)
            ORDER BY e.event_date DESC, e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($club_ids)), ...$club_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        showAlert('Invalid security token. Please try again.', 'error');
        redirect('manage_events.php');
    }
    
    switch ($action) {
        case 'create_event':
            $club_id = (int)($_POST['club_id'] ?? 0);
            $title = sanitize_input($_POST['title'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $event_date = $_POST['event_date'] ?? '';
            
            // Validate input
            if (empty($title) || empty($club_id)) {
                showAlert('Please fill in all required fields.', 'error');
                break;
            }
            
            // Check if user can manage this club
            if ($user_role !== 'admin' && !userManagesClub($user_id, $club_id)) {
                showAlert('You do not have permission to create events for this club.', 'error');
                break;
            }
            
            // Insert event
            $sql = "INSERT INTO events (club_id, title, description, event_date, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $event_date = !empty($event_date) ? $event_date : null;
            $stmt->bind_param("isss", $club_id, $title, $description, $event_date);
            
            if ($stmt->execute()) {
                showAlert('Event created successfully!', 'success');
                
                // Log activity
                $club_name = '';
                $club_sql = "SELECT name FROM clubs WHERE id = ?";
                $club_stmt = $conn->prepare($club_sql);
                $club_stmt->bind_param("i", $club_id);
                $club_stmt->execute();
                $club_result = $club_stmt->get_result();
                if ($club_row = $club_result->fetch_assoc()) {
                    $club_name = $club_row['name'];
                }
                
                logAdminActivity('Created Event', "Event: $title for $club_name", $_SESSION['username']);
            } else {
                showAlert('Error creating event. Please try again.', 'error');
            }
            break;
            
        case 'edit_event':
            $event_id = (int)($_POST['event_id'] ?? 0);
            $club_id = (int)($_POST['club_id'] ?? 0);
            $title = sanitize_input($_POST['title'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $event_date = $_POST['event_date'] ?? '';
            
            // Validate input
            if (empty($title) || empty($club_id) || empty($event_id)) {
                showAlert('Please fill in all required fields.', 'error');
                break;
            }
            
            // Check if user can manage this club
            if ($user_role !== 'admin' && !userManagesClub($user_id, $club_id)) {
                showAlert('You do not have permission to edit this event.', 'error');
                break;
            }
            
            // Update event
            $sql = "UPDATE events SET title = ?, description = ?, event_date = ? WHERE id = ? AND club_id = ?";
            $stmt = $conn->prepare($sql);
            $event_date = !empty($event_date) ? $event_date : null;
            $stmt->bind_param("sssii", $title, $description, $event_date, $event_id, $club_id);
            
            if ($stmt->execute()) {
                showAlert('Event updated successfully!', 'success');
                logAdminActivity('Updated Event', "Event ID: $event_id", $_SESSION['username']);
            } else {
                showAlert('Error updating event. Please try again.', 'error');
            }
            break;
            
        case 'delete_event':
            $event_id = (int)($_POST['event_id'] ?? 0);
            
            if (empty($event_id)) {
                showAlert('Invalid event ID.', 'error');
                break;
            }
            
            // Get event details for permission check
            $sql = "SELECT e.*, c.name as club_name FROM events e 
                    JOIN clubs c ON e.club_id = c.id WHERE e.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $event = $result->fetch_assoc();
            
            if (!$event) {
                showAlert('Event not found.', 'error');
                break;
            }
            
            // Check permissions
            if ($user_role !== 'admin' && !userManagesClub($user_id, $event['club_id'])) {
                showAlert('You do not have permission to delete this event.', 'error');
                break;
            }
            
            // Delete event
            $sql = "DELETE FROM events WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $event_id);
            
            if ($stmt->execute()) {
                showAlert('Event deleted successfully!', 'success');
                logAdminActivity('Deleted Event', "Event: {$event['title']} from {$event['club_name']}", $_SESSION['username']);
            } else {
                showAlert('Error deleting event. Please try again.', 'error');
            }
            break;
    }
    
    redirect('manage_events.php');
}

// Get managed clubs and events
$managed_clubs = getManagedClubs($conn, $user_id, $user_role);
$club_ids = array_column($managed_clubs, 'id');
$events = getClubEvents($conn, $club_ids);

// Get specific event for editing
$edit_event = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($events as $event) {
        if ($event['id'] == $edit_id) {
            $edit_event = $event;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - ICS Club Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .event-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
        }
        
        .event-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .club-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .upcoming-event {
            border-left: 4px solid #28a745;
        }
        
        .past-event {
            border-left: 4px solid #6c757d;
            opacity: 0.8;
        }
        
        .no-events {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-calendar-plus me-3"></i>Manage Events</h1>
                    <p class="mb-0">Create and manage events for your clubs</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="dashboard.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                    <a href="events.php" class="btn btn-outline-light">
                        <i class="fas fa-eye me-2"></i>View Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php displayAlert(); ?>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($managed_clubs); ?></div>
                    <div class="text-muted">Managed Clubs</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($events); ?></div>
                    <div class="text-muted">Total Events</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">
                        <?php 
                        $upcoming = array_filter($events, function($e) {
                            return $e['event_date'] && new DateTime($e['event_date']) > new DateTime();
                        });
                        echo count($upcoming);
                        ?>
                    </div>
                    <div class="text-muted">Upcoming Events</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">
                        <?php 
                        $this_month = array_filter($events, function($e) {
                            return $e['created_at'] && date('Y-m', strtotime($e['created_at'])) === date('Y-m');
                        });
                        echo count($this_month);
                        ?>
                    </div>
                    <div class="text-muted">This Month</div>
                </div>
            </div>
        </div>
        
        <!-- Event Form -->
        <div class="form-section">
            <h3><i class="fas fa-plus-circle me-2"></i><?php echo $edit_event ? 'Edit Event' : 'Create New Event'; ?></h3>
            
            <?php if (empty($managed_clubs)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You are not managing any clubs. Contact an administrator to be assigned as a club manager.
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_event ? 'edit_event' : 'create_event'; ?>">
                    <?php if ($edit_event): ?>
                        <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="club_id" class="form-label">Club *</label>
                                <select class="form-select" name="club_id" id="club_id" required>
                                    <option value="">Select a club</option>
                                    <?php foreach ($managed_clubs as $club): ?>
                                        <option value="<?php echo $club['id']; ?>" 
                                                <?php echo ($edit_event && $edit_event['club_id'] == $club['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($club['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date & Time</label>
                                <input type="datetime-local" class="form-control" name="event_date" id="event_date"
                                       value="<?php echo ($edit_event && $edit_event['event_date']) ? date('Y-m-d\TH:i', strtotime($edit_event['event_date'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Event Title *</label>
                        <input type="text" class="form-control" name="title" id="title" required
                               value="<?php echo $edit_event ? htmlspecialchars($edit_event['title']) : ''; ?>"
                               placeholder="Enter event title">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Event Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"
                                  placeholder="Describe the event, activities, requirements, etc."><?php echo $edit_event ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $edit_event ? 'Update Event' : 'Create Event'; ?>
                        </button>
                        
                        <?php if ($edit_event): ?>
                            <a href="manage_events.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Events List -->
        <div class="row">
            <div class="col-12">
                <h3><i class="fas fa-list me-2"></i>Your Events</h3>
                
                <?php if (empty($events)): ?>
                    <div class="no-events">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h4>No Events Yet</h4>
                        <p>You haven't created any events yet. Use the form above to create your first event!</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($events as $event): ?>
                            <?php
                            $is_upcoming = $event['event_date'] && new DateTime($event['event_date']) > new DateTime();
                            $card_class = $is_upcoming ? 'upcoming-event' : 'past-event';
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card event-card <?php echo $card_class; ?>">
                                    <div class="event-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                            <span class="club-badge">
                                                <?php echo htmlspecialchars($event['club_initials']); ?>
                                            </span>
                                        </div>
                                        <small class="text-light">
                                            <?php echo htmlspecialchars($event['club_name']); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="card-body">
                                        <?php if ($event['event_date']): ?>
                                            <p class="card-text">
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                <?php echo formatDate($event['event_date']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['description']): ?>
                                            <p class="card-text">
                                                <?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 100))); ?>
                                                <?php if (strlen($event['description']) > 100): ?>
                                                    <span class="text-muted">...</span>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Created <?php echo formatDate($event['created_at'], 'M j, Y'); ?>
                                        </small>
                                        
                                        <div class="event-actions">
                                            <a href="?edit=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['title']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the event "<strong id="eventTitle"></strong>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" id="deleteEventId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Event
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(eventId, eventTitle) {
            document.getElementById('deleteEventId').value = eventId;
            document.getElementById('eventTitle').textContent = eventTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const title = document.getElementById('title').value.trim();
                    const clubId = document.getElementById('club_id').value;
                    
                    if (!title) {
                        e.preventDefault();
                        alert('Please enter an event title.');
                        document.getElementById('title').focus();
                        return false;
                    }
                    
                    if (!clubId) {
                        e.preventDefault();
                        alert('Please select a club.');
                        document.getElementById('club_id').focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>