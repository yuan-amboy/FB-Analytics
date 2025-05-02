<?php
// Database connection
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "facebook_analytics_db"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get counts from each table (only first 50 users)
function getCount($conn, $table, $column = 'user_id') {
    // List tables that do NOT have a user_id column
    $tablesWithoutUserId = ['hashtags'];

    // Check if table has user_id or not
    if (in_array($table, $tablesWithoutUserId)) {
        $sql = "SELECT COUNT(*) as count FROM $table";
    } else {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE $column <= 50";
    }

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

// Get counts
$userCount = getCount($conn, 'users');
$postCount = getCount($conn, 'posts', 'user_id');
$commentCount = getCount($conn, 'comments', 'user_id');
$likeCount = getCount($conn, 'likes', 'user_id');
$shareCount = getCount($conn, 'shares', 'user_id');
$followerCount = getCount($conn, 'followers', 'followed_user');
$hashtagCount = getCount($conn, 'hashtags', 'user_id');

// Get most active users (by posts, only first 50 users)
$sql = "SELECT u.name, COUNT(p.post_id) as post_count
        FROM users u
        JOIN posts p ON u.user_id = p.user_id
        WHERE u.user_id <= 50
        GROUP BY u.user_id
        ORDER BY post_count DESC
        LIMIT 5";
$mostActiveUsers = $conn->query($sql);

// Get most popular posts (by likes + comments + shares, only first 50 author users)
$sql = "SELECT p.post_id, LEFT(p.content_post, 50) as content, u.name as author,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
        (SELECT COUNT(*) FROM shares WHERE post_id = p.post_id) as share_count,
        ((SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) + 
         (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) + 
         (SELECT COUNT(*) FROM shares WHERE post_id = p.post_id)) as total_engagement
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.user_id <= 50
        ORDER BY total_engagement DESC
        LIMIT 5";
$popularPosts = $conn->query($sql);

// Get most used hashtags (only from first 50 users)
$sql = "SELECT hashtag, COUNT(*) as count
        FROM hashtags
        GROUP BY hashtag
        ORDER BY count DESC
        LIMIT 10";
$popularHashtags = $conn->query($sql);

// Get user engagement over time (last 7 days, only first 50 users)
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count
        FROM posts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id <= 50
        GROUP BY DATE(created_at)
        ORDER BY date";
$postsOverTime = $conn->query($sql);

$sql = "SELECT DATE(commented_at) as date, COUNT(*) as count
        FROM comments
        WHERE commented_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id <= 50
        GROUP BY DATE(commented_at)
        ORDER BY date";
$commentsOverTime = $conn->query($sql);

$sql = "SELECT DATE(liked_at) as date, COUNT(*) as count
        FROM likes
        WHERE liked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id <= 50
        GROUP BY DATE(liked_at)
        ORDER BY date";
$likesOverTime = $conn->query($sql);

// Prepare chart data
$chartData = [];
$dateLabels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabels[] = date('M d', strtotime($date));
    $chartData[$date] = [
        'posts' => 0,
        'comments' => 0,
        'likes' => 0
    ];
}

if ($postsOverTime->num_rows > 0) {
    while ($row = $postsOverTime->fetch_assoc()) {
        if (isset($chartData[$row['date']])) {
            $chartData[$row['date']]['posts'] = (int)$row['count'];
        }
    }
}
if ($commentsOverTime->num_rows > 0) {
    while ($row = $commentsOverTime->fetch_assoc()) {
        if (isset($chartData[$row['date']])) {
            $chartData[$row['date']]['comments'] = (int)$row['count'];
        }
    }
}
if ($likesOverTime->num_rows > 0) {
    while ($row = $likesOverTime->fetch_assoc()) {
        if (isset($chartData[$row['date']])) {
            $chartData[$row['date']]['likes'] = (int)$row['count'];
        }
    }
}

$postData = array_column($chartData, 'posts');
$commentData = array_column($chartData, 'comments');
$likeData = array_column($chartData, 'likes');

// User Growth Over Time (only first 50 users)
$sql = "SELECT DATE(joined_date) as date, COUNT(*) as count
        FROM users
        WHERE user_id <= 50
        GROUP BY DATE(joined_date)
        ORDER BY date";

$userGrowth = $conn->query($sql);
$userGrowthData = [];
$userGrowthLabels = [];
$cumulativeUsers = 0;

if ($userGrowth->num_rows > 0) {
    while ($row = $userGrowth->fetch_assoc()) {
        $userGrowthLabels[] = date('M d', strtotime($row['date']));
        $cumulativeUsers += (int)$row['count'];
        $userGrowthData[] = $cumulativeUsers;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        header {
            text-align: center;
        }

        header h1, header p {
            margin-left: auto;
            margin-right: auto;
        }

        header nav {
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Dashboard</h1>
            <p>Quick overview of system status and key metrics</p>
            <nav>
                <a href="activity_history.php">Activity History</a>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="analytics.php">Analytics & Reporting</a>
            </nav>
        </header>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Users</h3>
                <p class="stat-number"><?php echo $userCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Posts</h3>
                <p class="stat-number"><?php echo $postCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Comments</h3>
                <p class="stat-number"><?php echo $commentCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Likes</h3>
                <p class="stat-number"><?php echo $likeCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Shares</h3>
                <p class="stat-number"><?php echo $shareCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Followers</h3>
                <p class="stat-number"><?php echo $followerCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Hashtags</h3>
                <p class="stat-number"><?php echo $hashtagCount; ?></p>
            </div>
        </div>

<!-- Charts -->
<div class="charts-container">
    <div class="chart-card wide">
        <h3>Engagement Over Time</h3>
        <canvas id="engagementChart"></canvas>
    </div>
    <div class="chart-card user-growth">
        <h3>User Growth</h3>
        <canvas id="userGrowthChart"></canvas>
    </div>
    <div class="chart-card engagement-distribution">
        <h3>Engagement Distribution</h3>
        <canvas id="engagementDistributionChart"></canvas>
    </div>
</div>

        <!-- Data Tables -->
        <div class="data-tables">
            <div class="table-card">
                <h3>Most Active Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Posts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($mostActiveUsers->num_rows > 0): ?>
                            <?php while ($row = $mostActiveUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                    <td><?php echo $row["post_count"]; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card wide">
                <h3>Most Popular Posts</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Post Content</th>
                            <th>Author</th>
                            <th>Likes</th>
                            <th>Comments</th>
                            <th>Shares</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($popularPosts->num_rows > 0): ?>
                            <?php while ($row = $popularPosts->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["content"]); ?>...</td>
                                    <td><?php echo htmlspecialchars($row["author"]); ?></td>
                                    <td><?php echo $row["like_count"]; ?></td>
                                    <td><?php echo $row["comment_count"]; ?></td>
                                    <td><?php echo $row["share_count"]; ?></td>
                                    <td><?php echo $row["total_engagement"]; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3>Popular Hashtags</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Hashtag</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($popularHashtags->num_rows > 0): ?>
                            <?php while ($row = $popularHashtags->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($row["hashtag"]); ?></td>
                                    <td><?php echo $row["count"]; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script>
        const engagementCtx = document.getElementById('engagementChart').getContext('2d');
        const engagementChart = new Chart(engagementCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dateLabels); ?>,
                datasets: [
                    {
                        label: 'Posts',
                        data: <?php echo json_encode($postData); ?>,
                        borderColor: '#1877f2',
                        backgroundColor: 'rgba(24, 119, 242, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Comments',
                        data: <?php echo json_encode($commentData); ?>,
                        borderColor: '#42b72a',
                        backgroundColor: 'rgba(66, 183, 42, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Likes',
                        data: <?php echo json_encode($likeData); ?>,
                        borderColor: '#f02849',
                        backgroundColor: 'rgba(240, 40, 73, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Activity'
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });

        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($userGrowthLabels); ?>,
                datasets: [{
                    label: 'Total Users',
                    data: <?php echo json_encode($userGrowthData); ?>,
                    borderColor: '#1877f2',
                    backgroundColor: 'rgba(24, 119, 242, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'User Growth Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Users'
                        }
                    }
                }
            }
        });

        const distributionCtx = document.getElementById('engagementDistributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'pie',
            data: {
                labels: ['Posts', 'Comments', 'Likes', 'Shares'],
                datasets: [{
                    data: [<?php echo $postCount; ?>, <?php echo $commentCount; ?>, <?php echo $likeCount; ?>, <?php echo $shareCount; ?>],
                    backgroundColor: ['#1877f2', '#42b72a', '#f02849', '#ff7a00'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Types of Engagement'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html>
