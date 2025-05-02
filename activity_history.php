<?php

// DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "facebook_analytics_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT 
            'post' as type, 
            p.post_id as id,
            p.content_post as content,
            p.image,
            p.created_at as timestamp,
            u.user_id,
            u.name as author_name,
            NULL as action_user_name,
            NULL as action_user_id,
            GROUP_CONCAT(h.hashtag SEPARATOR ', ') as hashtags,
            NULL as original_content,
            NULL as related_content
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN hashtags h ON p.post_id = h.post_id
        WHERE p.user_id <= 50
        GROUP BY p.post_id

        UNION ALL

        SELECT 
            'share' as type,
            s.share_id as id,
            s.content_share as content,
            s.image as image,
            s.shared_at as timestamp,
            p.user_id as original_author_id,
            u1.name as author_name,
            u2.name as action_user_name,
            s.user_id as action_user_id,
            GROUP_CONCAT(h.hashtag SEPARATOR ', ') as hashtags,
            p.content_post as original_content,
            NULL as related_content
        FROM shares s
        JOIN posts p ON s.post_id = p.post_id
        JOIN users u1 ON p.user_id = u1.user_id
        JOIN users u2 ON s.user_id = u2.user_id
        LEFT JOIN hashtags h ON p.post_id = h.post_id
        WHERE s.user_id <= 50
        GROUP BY s.share_id

        UNION ALL

        SELECT 
            'comment' as type,
            c.comment_id as id,
            c.comment_text as content,
            NULL as image,
            c.commented_at as timestamp,
            p.user_id as original_author_id,
            u1.name as author_name,
            u2.name as action_user_name,
            c.user_id as action_user_id,
            NULL as hashtags,
            NULL as original_content,
            p.content_post as related_content
        FROM comments c
        JOIN posts p ON c.post_id = p.post_id
        JOIN users u1 ON p.user_id = u1.user_id
        JOIN users u2 ON c.user_id = u2.user_id
        WHERE c.user_id <= 50
        
        UNION ALL

        SELECT 
            'like' as type,
            l.like_id as id,
            NULL as content,
            NULL as image,
            l.liked_at as timestamp,
            p.user_id as original_author_id,
            u1.name as author_name,
            u2.name as action_user_name,
            l.user_id as action_user_id,
            NULL as hashtags,
            NULL as original_content,
            p.content_post as related_content
        FROM likes l
        JOIN posts p ON l.post_id = p.post_id
        JOIN users u1 ON p.user_id = u1.user_id
        JOIN users u2 ON l.user_id = u2.user_id
        WHERE l.user_id <= 50

        ORDER BY timestamp DESC
        LIMIT 100";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity History</title>
    <link rel="stylesheet" href="activity_history.css">
    <style>
        /* Header Styles */
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
            <h1>Activity History</h1>
            <p>Track past user actions and system events</p>
            <nav>
                <a href="activity_history.php" class="active">Activity History</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="analytics.php">Analytics & Reporting</a>
            </nav>
        </header>

        <main class="activity-container">
            <div class="activity-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($activity = $result->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div>
                                <span class="activity-type <?php echo $activity['type'] ?>-type">
                                    <?php echo strtoupper($activity['type']) ?>
                                </span>
                                
                                <?php if ($activity['type'] === 'post'): ?>
                                    <span> → Action by <strong><?php echo htmlspecialchars($activity['author_name']) ?></strong></span>
                                <?php endif; ?>

                                <?php if (in_array($activity['type'], ['share', 'comment', 'like'])): ?>
                                    <span> → Action by <strong><?php echo htmlspecialchars($activity['action_user_name']) ?></strong></span>
                                <?php endif; ?>

                                <span class="activity-meta">
                                    on <?php echo date('M j, Y \a\t g:i A', strtotime($activity['timestamp'])) ?>
                                </span>
                            </div>

                            <?php if (!empty($activity['content'])): ?>
                                <div class="activity-content">
                                    <?php echo str_replace("\n", '<br>', htmlspecialchars(trim($activity['content']))) ?>
                                    
                                    <?php if (!empty($activity['hashtags'])): ?>
                                        <div class="hashtags">
                                            Hashtags: <?php echo htmlspecialchars($activity['hashtags']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($activity['image'])): ?>
                                <div class="<?php echo $activity['type'] ?>-image">
                                    <?php 
                                        $image_files = explode(',', $activity['image']);
                                        foreach($image_files as $image_file): 
                                            $image_file = trim($image_file);
                                            if(!empty($image_file)):
                                    ?>
                                        <img src="images/<?php echo htmlspecialchars($image_file) ?>" alt="Post image">
                                    <?php 
                                            endif;
                                            endforeach; 
                                    ?>
                                </div>
                        <?php endif; ?>

                            <?php if ($activity['type'] == 'share'): ?>
                                <div class="original-post">
                                    <div class="original-post-meta">
                                        Original post by <strong><?php echo htmlspecialchars($activity['author_name']) ?></strong> 
                                        on <?php echo date('M j, Y \a\t g:i A', strtotime($activity['timestamp'])) ?>
                                    </div>
                                    <?php if (!empty($activity['original_content'])): ?>
                                        <div class="original-post-content">
                                            <?php echo str_replace("\n", '<br>', htmlspecialchars(trim($activity['original_content']))) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($activity['hashtags'])): ?>
                                        <div class="hashtags">
                                            Hashtags: <?php echo htmlspecialchars($activity['hashtags']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($activity['type'] == 'like'): ?>
                                <div class="related-post">
                                    <div class="related-post-meta">
                                        Liked post by <strong><?php echo htmlspecialchars($activity['author_name']) ?></strong>:
                                    </div>
                                    <?php if (!empty($activity['related_content'])): ?>
                                        <div class="related-post-content">
                                            <?php echo str_replace("\n", '<br>', htmlspecialchars(trim($activity['related_content']))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($activity['type'] == 'comment'): ?>
                                <div class="related-post">
                                    <div class="related-post-meta">
                                        Commented on post by <strong><?php echo htmlspecialchars($activity['author_name']) ?></strong>:
                                    </div>
                                    <?php if (!empty($activity['related_content'])): ?>
                                        <div class="related-post-content">
                                            <?php echo str_replace("\n", '<br>', htmlspecialchars(trim($activity['related_content']))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-activities">
                        <p>No activities found in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
