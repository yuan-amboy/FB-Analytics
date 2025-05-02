<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "facebook_analytics_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Calculate activity by hour of day (first 50 users only)
$sql = "SELECT HOUR(created_at) as hour, COUNT(*) as count 
        FROM posts 
        WHERE user_id <= 50
        GROUP BY HOUR(created_at) 
        ORDER BY hour";
$postsByHour = $conn->query($sql);
$hourlyPostData = array_fill(0, 24, 0);

if ($postsByHour->num_rows > 0) {
    while($row = $postsByHour->fetch_assoc()) {
        $hourlyPostData[(int)$row['hour']] = (int)$row['count'];
    }
}

$sql = "SELECT HOUR(c.commented_at) as hour, COUNT(*) as count 
        FROM comments c
        JOIN posts p ON c.post_id = p.post_id
        WHERE c.user_id <= 50 OR p.user_id <= 50
        GROUP BY HOUR(c.commented_at) 
        ORDER BY hour";
$commentsByHour = $conn->query($sql);
$hourlyCommentData = array_fill(0, 24, 0);

if ($commentsByHour->num_rows > 0) {
    while($row = $commentsByHour->fetch_assoc()) {
        $hourlyCommentData[(int)$row['hour']] = (int)$row['count'];
    }
}

$sql = "SELECT HOUR(l.liked_at) as hour, COUNT(*) as count 
        FROM likes l
        JOIN posts p ON l.post_id = p.post_id
        WHERE l.user_id <= 50 OR p.user_id <= 50
        GROUP BY HOUR(l.liked_at) 
        ORDER BY hour";
$likesByHour = $conn->query($sql);
$hourlyLikeData = array_fill(0, 24, 0);

if ($likesByHour->num_rows > 0) {
    while($row = $likesByHour->fetch_assoc()) {
        $hourlyLikeData[(int)$row['hour']] = (int)$row['count'];
    }
}

// Get top user interactions (including shares and nested interactions)
$sql = "SELECT 
            u1.name as user1, 
            u2.name as user2,
            orig.name as original_poster,
            COUNT(*) as interaction_count,
            GROUP_CONCAT(DISTINCT interaction_type SEPARATOR ', ') as interaction_types
        FROM (
            -- Direct interactions with shared posts (comments/likes on shared content)
            SELECT 
                l.user_id as user1_id,
                s.user_id as user2_id,
                p.user_id as original_poster_id,
                'like_shared_post' as interaction_type
            FROM likes l
            JOIN shares s ON l.post_id = s.share_id
            JOIN posts p ON s.post_id = p.post_id
            WHERE (l.user_id <= 50 OR s.user_id <= 50) AND l.user_id != s.user_id
            
            UNION ALL
            
            SELECT 
                c.user_id as user1_id,
                s.user_id as user2_id,
                p.user_id as original_poster_id,
                'comment_shared_post' as interaction_type
            FROM comments c
            JOIN shares s ON c.post_id = s.share_id
            JOIN posts p ON s.post_id = p.post_id
            WHERE (c.user_id <= 50 OR s.user_id <= 50) AND c.user_id != s.user_id
            
            UNION ALL
            
            -- Shares of other users' posts
            SELECT 
                s.user_id as user1_id,
                p.user_id as user2_id,
                p.user_id as original_poster_id,
                'shared_post' as interaction_type
            FROM shares s
            JOIN posts p ON s.post_id = p.post_id
            WHERE (s.user_id <= 50 OR p.user_id <= 50) AND s.user_id != p.user_id
            
            UNION ALL
            
            -- Direct interactions (comments/likes on original posts)
            SELECT 
                c.user_id as user1_id,
                p.user_id as user2_id,
                p.user_id as original_poster_id,
                'comment_post' as interaction_type
            FROM comments c
            JOIN posts p ON c.post_id = p.post_id
            WHERE (p.user_id <= 50 OR c.user_id <= 50) AND p.user_id != c.user_id
            
            UNION ALL
            
            SELECT 
                l.user_id as user1_id,
                p.user_id as user2_id,
                p.user_id as original_poster_id,
                'like_post' as interaction_type
            FROM likes l
            JOIN posts p ON l.post_id = p.post_id
            WHERE (p.user_id <= 50 OR l.user_id <= 50) AND p.user_id != l.user_id
        ) as interactions
        JOIN users u1 ON interactions.user1_id = u1.user_id
        JOIN users u2 ON interactions.user2_id = u2.user_id
        JOIN users orig ON interactions.original_poster_id = orig.user_id
        GROUP BY user1_id, user2_id, original_poster_id
        ORDER BY interaction_count DESC
        LIMIT 10";
$topUserPairs = $conn->query($sql);

// Get most engaging content types (first 50 users only)
$sql = "SELECT 
            CASE 
                WHEN image IS NULL OR image = '' THEN 'Text Only'
                ELSE 'With Image'
            END as content_type,
            COUNT(*) as post_count,
            AVG((SELECT COUNT(*) FROM likes l WHERE l.post_id = posts.post_id AND l.user_id <= 50)) as avg_likes,
            AVG((SELECT COUNT(*) FROM comments c WHERE c.post_id = posts.post_id AND c.user_id <= 50)) as avg_comments,
            AVG((SELECT COUNT(*) FROM shares s WHERE s.post_id = posts.post_id AND s.user_id <= 50)) as avg_shares
        FROM posts
        WHERE user_id <= 50
        GROUP BY content_type";
$contentTypeStats = $conn->query($sql);

// Get hashtag performance (first 50 users only)
$sql = "SELECT 
            h.hashtag,
            COUNT(*) as usage_count,
            AVG((SELECT COUNT(*) FROM likes l WHERE l.post_id = h.post_id AND l.user_id <= 50)) as avg_likes,
            AVG((SELECT COUNT(*) FROM comments c WHERE c.post_id = h.post_id AND c.user_id <= 50)) as avg_comments,
            AVG((SELECT COUNT(*) FROM shares s WHERE s.post_id = h.post_id AND s.user_id <= 50)) as avg_shares
        FROM hashtags h
        JOIN posts p ON h.post_id = p.post_id
        WHERE p.user_id <= 50
        GROUP BY h.hashtag
        HAVING usage_count > 0
        ORDER BY usage_count DESC
        LIMIT 10";
$hashtagPerformance = $conn->query($sql);

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics</title>
    <link rel="stylesheet" href="analytics.css">
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
        
        .user-filter-notice {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-style: italic;
            color: #666;
        }
        
        .no-data {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        .interaction-description {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Analytics & Reporting</h1>
            <p>Detailed insights from collected data</p>
            <nav>
                <a href="activity_history.php">Activity History</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="analytics.php" class="active">Analytics & Reporting</a>
            </nav>
        </header>

        <div class="analytics-section">
            <h2>Activity by Time of Day</h2>
            <div class="chart-card wide">
                <canvas id="hourlyActivityChart"></canvas>
            </div>
        </div>
        
        <!-- Top User Interactions section -->
        <div class="analytics-section">
            <h2>Top User Interactions</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User Interaction</th>
                            <th>Interaction Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($topUserPairs) && $topUserPairs->num_rows > 0): ?>
                            <?php while($row = $topUserPairs->fetch_assoc()): 
                                // Format interaction description based on types
                                $user1 = htmlspecialchars($row["user1"]);
                                $user2 = htmlspecialchars($row["user2"]);
                                $originalPoster = htmlspecialchars($row["original_poster"]);
                                $types = explode(', ', $row["interaction_types"]);
                                
                                $interactionDesc = "";
                                
                                // Check for likes on shared posts
                                $hasLikedShared = in_array('like_shared_post', $types);
                                
                                // Check for comments on shared posts
                                $hasCommentedShared = in_array('comment_shared_post', $types);
                                
                                // Check for shares of posts
                                $hasSharedPost = in_array('shared_post', $types);
                                
                                // Check for likes on original posts
                                $hasLikedPost = in_array('like_post', $types);
                                
                                // Check for comments on original posts
                                $hasCommentedPost = in_array('comment_post', $types);
                                
                                // Build interaction description
                                $interactions = [];
                                
                                if ($hasLikedShared) {
                                    $interactions[] = "liked";
                                }
                                
                                if ($hasCommentedShared) {
                                    $interactions[] = "commented on";
                                }
                                
                                if ($hasSharedPost) {
                                    $interactions[] = "shared";
                                }
                                
                                if ($hasLikedPost) {
                                    $interactions[] = "liked";
                                }
                                
                                if ($hasCommentedPost) {
                                    $interactions[] = "commented on";
                                }
                                
                                $interactionList = implode(" and ", $interactions);
                                
                                if ($user2 === $originalPoster) {
                                    $interactionDesc = "$user1 $interactionList $user2's post.";
                                } else {
                                    $interactionDesc = "$user1 $interactionList $user2's shared post from $originalPoster.";
                                }
                            ?>
                                <tr>
                                    <td class="interaction-description"><?= $interactionDesc ?></td>
                                    <td><?= $row["interaction_count"] ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="no-data">No interaction data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="analytics-section">
            <h2>Content Type Performance</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Content Type</th>
                            <th>Posts</th>
                            <th>Avg Likes</th>
                            <th>Avg Comments</th>
                            <th>Avg Shares</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($contentTypeStats) && $contentTypeStats->num_rows > 0): ?>
                            <?php while($row = $contentTypeStats->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row["content_type"]) ?></td>
                                    <td><?= $row["post_count"] ?></td>
                                    <td><?= round($row["avg_likes"], 1) ?></td>
                                    <td><?= round($row["avg_comments"], 1) ?></td>
                                    <td><?= round($row["avg_shares"], 1) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="no-data">No content type data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hashtag Performance section (now inside dashboard-container) -->
        <div class="analytics-section">
            <h2>Hashtag Performance</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Hashtag</th>
                            <th>Usage Count</th>
                            <th>Avg Likes</th>
                            <th>Avg Comments</th>
                            <th>Avg Shares</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($hashtagPerformance) && $hashtagPerformance->num_rows > 0): ?>
                            <?php while($row = $hashtagPerformance->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($row["hashtag"] ?? '') ?></td>
                                    <td><?= $row["usage_count"] ?? 0 ?></td>
                                    <td><?= round($row["avg_likes"] ?? 0, 1) ?></td>
                                    <td><?= round($row["avg_comments"] ?? 0, 1) ?></td>
                                    <td><?= round($row["avg_shares"] ?? 0, 1) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="no-data">No hashtag data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- Proper closing of dashboard-container div -->

    <script>
        // Hourly Activity Chart
        const hourlyCtx = document.getElementById('hourlyActivityChart').getContext('2d');
        const hourlyChart = new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [
                    {
                        label: 'Posts',
                        data: <?= json_encode($hourlyPostData) ?>,
                        backgroundColor: 'rgba(24, 119, 242, 0.7)',
                        borderColor: '#1877f2',
                        borderWidth: 1
                    },
                    {
                        label: 'Comments',
                        data: <?= json_encode($hourlyCommentData) ?>,
                        backgroundColor: 'rgba(66, 183, 42, 0.7)',
                        borderColor: '#42b72a',
                        borderWidth: 1
                    },
                    {
                        label: 'Likes',
                        data: <?= json_encode($hourlyLikeData) ?>,
                        backgroundColor: 'rgba(240, 40, 73, 0.7)',
                        borderColor: '#f02849',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Activity by Hour of Day'
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Activity Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hour of Day'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
