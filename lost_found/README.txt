UniFlow Lost & Found - Fresh Module
===================================

This version uses the EXISTING UniFlow login/session. There is no separate Lost & Found login.

EXPECTED EXISTING STUDENT TABLE:
students(student_id, name, email, password, ...)

INSTALLATION
------------
1. Delete the old C:\xampp\htdocs\uniflow\lost_found folder.
2. Extract this module as C:\xampp\htdocs\uniflow\lost_found\
3. In phpMyAdmin, select uniflow_db -> Import -> database.sql.
4. IMPORTANT: database.sql DROPS and recreates ONLY lost_found_posts, lost_found_likes and lost_found_comments.
   Old Lost & Found posts/comments/likes will be cleared. The students table is NOT touched.
   For an existing installation, run role_migration.sql instead; it preserves existing posts.
5. Make sure your normal UniFlow login sets $_SESSION['student_id'] and (optionally) $_SESSION['student_name'].
6. Open: http://localhost/uniflow/lost_found/

FEATURES
--------
- One default UniFlow login; no separate LF login.
- Technical, Administrative and Proctorial admins can create posts; students can create posts and comments.
- Lost & Found admins/moderators and the Main Admin can edit or delete any post, update moderation status and internal notes, and delete comments. Technical and Proctorial roles do not moderate Lost & Found.
- Every new or user-edited post stays Pending until a moderator approves it. Approved and resolved posts appear in the public feed.
- Moderators open the inbox from the Moderation link in the Lost & Found navigation, or directly at moderator.php.
- Shows names of people who liked a post.
- Facebook-style feed.
- Search + Lost/Found filter.
- Create post page.
- Three-dot menu appears ONLY on the owner's own posts.
- Owner can edit/delete only their own post.
- No View Details button.
- Logout goes through the existing ../logout.php.
- Orange UniFlow UI and the same overall alignment/design are preserved.


Login behavior:
- Lost & Found uses the existing UniFlow login.php and returns users to lost_found/index.php after login, including after a required password change.
- Logout from Lost & Found uses local logout.php and returns to ../index.php.
