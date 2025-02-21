<?php
session_start();

// Conexión a la base de datos SQLite (se creará el archivo si no existe)
$db = new PDO('sqlite:../databases/purple.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Crear tablas si no existen (agregamos la columna user_id para registros multiusuario)
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL
)");
$db->exec("CREATE TABLE IF NOT EXISTS teachers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL
)");
$db->exec("CREATE TABLE IF NOT EXISTS classrooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL
)");
$db->exec("CREATE TABLE IF NOT EXISTS classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    subject_id INTEGER,
    teacher_id INTEGER,
    classroom_id INTEGER,
    start_time TEXT,
    end_time TEXT,
    FOREIGN KEY(subject_id) REFERENCES subjects(id),
    FOREIGN KEY(teacher_id) REFERENCES teachers(id),
    FOREIGN KEY(classroom_id) REFERENCES classrooms(id)
)");

// Insertar usuario inicial si no existe
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute(['jocarsa']);
if ($stmt->fetchColumn() == 0) {
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute(['jocarsa', 'jocarsa']);
}

// Manejo de cierre de sesión
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: index.php");
    exit;
}

// Manejo de inicio de sesión si no está logueado
if(!isset($_SESSION['user'])){
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['password'])){
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$_POST['username'], $_POST['password']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user){
            $_SESSION['user'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Credenciales inválidas";
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Iniciar sesión</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <div class="login-container">
        <h2>Iniciar sesión</h2>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <label>Usuario:</label>
            <input type="text" name="username" required>
            <label>Contraseña:</label>
            <input type="password" name="password" required>
            <input type="submit" value="Entrar">
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Función de redirección
function redirect($url){
    header("Location: $url");
    exit;
}

// Determinar la página y acción a mostrar
$page   = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Panel de Administración</h1>
    <a href="?logout=1" class="logout">Cerrar sesión</a>
</header>
<div class="container">
    <nav>
        <ul>
            <li><a href="index.php?page=dashboard">Inicio</a></li>
            <li><a href="index.php?page=subjects">Asignaturas</a></li>
            <li><a href="index.php?page=teachers">Profesores</a></li>
            <li><a href="index.php?page=classrooms">Aulas</a></li>
            <li><a href="index.php?page=classes">Clases</a></li>
        </ul>
    </nav>
    <main>
    <?php
    // Página de inicio
    if($page == 'dashboard'){
        echo "<h2>Bienvenido, " . htmlspecialchars($_SESSION['user']) . "</h2>";
        echo "<p>Seleccione una opción del menú.</p>";
    }
    
    // CRUD de Asignaturas
    elseif($page == 'subjects'){
        echo "<h2>Asignaturas</h2>";
        if($action == 'add'){
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("INSERT INTO subjects (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                redirect("index.php?page=subjects");
            } else {
                ?>
                <form method="post">
                    <label>Nombre de la asignatura:</label>
                    <input type="text" name="name" required>
                    <input type="submit" value="Agregar Asignatura">
                </form>
                <?php
            }
        }
        elseif($action == 'edit' && isset($_GET['id'])){
            $id = $_GET['id'];
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("UPDATE subjects SET name = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $id, $user_id]);
                redirect("index.php?page=subjects");
            } else {
                $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <form method="post">
                    <label>Nombre de la asignatura:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                    <input type="submit" value="Actualizar Asignatura">
                </form>
                <?php
            }
        }
        elseif($action == 'delete' && isset($_GET['id'])){
            $stmt = $db->prepare("DELETE FROM subjects WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            redirect("index.php?page=subjects");
        }
        else{
            echo '<a href="index.php?page=subjects&action=add" class="button">Agregar Asignatura</a>';
            $stmt = $db->prepare("SELECT * FROM subjects WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr>";
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>
                        <a href='index.php?page=subjects&action=edit&id=" . $row['id'] . "'>Editar</a> |
                        <a href='index.php?page=subjects&action=delete&id=" . $row['id'] . "' onclick=\"return confirm('¿Está seguro?')\">Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // CRUD de Profesores
    elseif($page == 'teachers'){
        echo "<h2>Profesores</h2>";
        if($action == 'add'){
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("INSERT INTO teachers (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                redirect("index.php?page=teachers");
            } else {
                ?>
                <form method="post">
                    <label>Nombre del profesor:</label>
                    <input type="text" name="name" required>
                    <input type="submit" value="Agregar Profesor">
                </form>
                <?php
            }
        }
        elseif($action == 'edit' && isset($_GET['id'])){
            $id = $_GET['id'];
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("UPDATE teachers SET name = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $id, $user_id]);
                redirect("index.php?page=teachers");
            } else {
                $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <form method="post">
                    <label>Nombre del profesor:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                    <input type="submit" value="Actualizar Profesor">
                </form>
                <?php
            }
        }
        elseif($action == 'delete' && isset($_GET['id'])){
            $stmt = $db->prepare("DELETE FROM teachers WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            redirect("index.php?page=teachers");
        }
        else{
            echo '<a href="index.php?page=teachers&action=add" class="button">Agregar Profesor</a>';
            $stmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr>";
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>
                        <a href='index.php?page=teachers&action=edit&id=" . $row['id'] . "'>Editar</a> |
                        <a href='index.php?page=teachers&action=delete&id=" . $row['id'] . "' onclick=\"return confirm('¿Está seguro?')\">Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // CRUD de Aulas
    elseif($page == 'classrooms'){
        echo "<h2>Aulas</h2>";
        if($action == 'add'){
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("INSERT INTO classrooms (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                redirect("index.php?page=classrooms");
            } else {
                ?>
                <form method="post">
                    <label>Nombre del aula:</label>
                    <input type="text" name="name" required>
                    <input type="submit" value="Agregar Aula">
                </form>
                <?php
            }
        }
        elseif($action == 'edit' && isset($_GET['id'])){
            $id = $_GET['id'];
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $name = $_POST['name'];
                $stmt = $db->prepare("UPDATE classrooms SET name = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $id, $user_id]);
                redirect("index.php?page=classrooms");
            } else {
                $stmt = $db->prepare("SELECT * FROM classrooms WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                $classroom = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <form method="post">
                    <label>Nombre del aula:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($classroom['name']); ?>" required>
                    <input type="submit" value="Actualizar Aula">
                </form>
                <?php
            }
        }
        elseif($action == 'delete' && isset($_GET['id'])){
            $stmt = $db->prepare("DELETE FROM classrooms WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            redirect("index.php?page=classrooms");
        }
        else{
            echo '<a href="index.php?page=classrooms&action=add" class="button">Agregar Aula</a>';
            $stmt = $db->prepare("SELECT * FROM classrooms WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo "<table>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr>";
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>
                        <a href='index.php?page=classrooms&action=edit&id=" . $row['id'] . "'>Editar</a> |
                        <a href='index.php?page=classrooms&action=delete&id=" . $row['id'] . "' onclick=\"return confirm('¿Está seguro?')\">Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // CRUD de Clases (combinando asignaturas, profesores, aulas, hora de inicio y fin)
    elseif($page == 'classes'){
        echo "<h2>Clases</h2>";
        if($action == 'add'){
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $subject_id   = $_POST['subject_id'];
                $teacher_id   = $_POST['teacher_id'];
                $classroom_id = $_POST['classroom_id'];
                $start_time   = $_POST['start_time'];
                $end_time     = $_POST['end_time'];
                $stmt = $db->prepare("INSERT INTO classes (user_id, subject_id, teacher_id, classroom_id, start_time, end_time) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$user_id, $subject_id, $teacher_id, $classroom_id, $start_time, $end_time]);
                redirect("index.php?page=classes");
            } else {
                // Obtener opciones filtradas por usuario
                $stmtSubjects = $db->prepare("SELECT * FROM subjects WHERE user_id = ?");
                $stmtSubjects->execute([$user_id]);
                $subjects   = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtTeachers = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
                $stmtTeachers->execute([$user_id]);
                $teachers   = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtClassrooms = $db->prepare("SELECT * FROM classrooms WHERE user_id = ?");
                $stmtClassrooms->execute([$user_id]);
                $classrooms = $stmtClassrooms->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <form method="post">
                    <label>Asignatura:</label>
                    <select name="subject_id" required>
                        <?php foreach($subjects as $subject){ ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php } ?>
                    </select>
                    
                    <label>Profesor:</label>
                    <select name="teacher_id" required>
                        <?php foreach($teachers as $teacher){ ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                        <?php } ?>
                    </select>
                    
                    <label>Aula:</label>
                    <select name="classroom_id" required>
                        <?php foreach($classrooms as $classroom){ ?>
                            <option value="<?php echo $classroom['id']; ?>"><?php echo htmlspecialchars($classroom['name']); ?></option>
                        <?php } ?>
                    </select>
                    
                    <label>Hora de inicio:</label>
                    <input type="datetime-local" name="start_time" required>
                    
                    <label>Hora de fin:</label>
                    <input type="datetime-local" name="end_time" required>
                    
                    <input type="submit" value="Agregar Clase">
                </form>
                <?php
            }
        }
        elseif($action == 'edit' && isset($_GET['id'])){
            $id = $_GET['id'];
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $subject_id   = $_POST['subject_id'];
                $teacher_id   = $_POST['teacher_id'];
                $classroom_id = $_POST['classroom_id'];
                $start_time   = $_POST['start_time'];
                $end_time     = $_POST['end_time'];
                $stmt = $db->prepare("UPDATE classes SET subject_id=?, teacher_id=?, classroom_id=?, start_time=?, end_time=? WHERE id=? AND user_id=?");
                $stmt->execute([$subject_id, $teacher_id, $classroom_id, $start_time, $end_time, $id, $user_id]);
                redirect("index.php?page=classes");
            } else {
                $stmt = $db->prepare("SELECT * FROM classes WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                $class = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmtSubjects = $db->prepare("SELECT * FROM subjects WHERE user_id = ?");
                $stmtSubjects->execute([$user_id]);
                $subjects   = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtTeachers = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
                $stmtTeachers->execute([$user_id]);
                $teachers   = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtClassrooms = $db->prepare("SELECT * FROM classrooms WHERE user_id = ?");
                $stmtClassrooms->execute([$user_id]);
                $classrooms = $stmtClassrooms->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <form method="post">
                    <label>Asignatura:</label>
                    <select name="subject_id" required>
                        <?php foreach($subjects as $subject){ ?>
                            <option value="<?php echo $subject['id']; ?>" <?php if($subject['id'] == $class['subject_id']) echo "selected"; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    
                    <label>Profesor:</label>
                    <select name="teacher_id" required>
                        <?php foreach($teachers as $teacher){ ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php if($teacher['id'] == $class['teacher_id']) echo "selected"; ?>>
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    
                    <label>Aula:</label>
                    <select name="classroom_id" required>
                        <?php foreach($classrooms as $classroom){ ?>
                            <option value="<?php echo $classroom['id']; ?>" <?php if($classroom['id'] == $class['classroom_id']) echo "selected"; ?>>
                                <?php echo htmlspecialchars($classroom['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    
                    <label>Hora de inicio:</label>
                    <input type="datetime-local" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($class['start_time'])); ?>" required>
                    
                    <label>Hora de fin:</label>
                    <input type="datetime-local" name="end_time" value="<?php echo date('Y-m-d\TH:i', strtotime($class['end_time'])); ?>" required>
                    
                    <input type="submit" value="Actualizar Clase">
                </form>
                <?php
            }
        }
        elseif($action == 'delete' && isset($_GET['id'])){
            $stmt = $db->prepare("DELETE FROM classes WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
            redirect("index.php?page=classes");
        }
        else{
            echo '<a href="index.php?page=classes&action=add" class="button">Agregar Clase</a>';
            $query = "SELECT classes.*, subjects.name as subject_name, teachers.name as teacher_name, classrooms.name as classroom_name
                      FROM classes
                      LEFT JOIN subjects ON classes.subject_id = subjects.id
                      LEFT JOIN teachers ON classes.teacher_id = teachers.id
                      LEFT JOIN classrooms ON classes.classroom_id = classrooms.id
                      WHERE classes.user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            echo "<table>";
            echo "<tr>
                    <th>ID</th>
                    <th>Asignatura</th>
                    <th>Profesor</th>
                    <th>Aula</th>
                    <th>Hora de inicio</th>
                    <th>Hora de fin</th>
                    <th>Acciones</th>
                  </tr>";
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['classroom_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['start_time']) . "</td>";
                echo "<td>" . htmlspecialchars($row['end_time']) . "</td>";
                echo "<td>
                        <a href='index.php?page=classes&action=edit&id=" . $row['id'] . "'>Editar</a> |
                        <a href='index.php?page=classes&action=delete&id=" . $row['id'] . "' onclick=\"return confirm('¿Está seguro?')\">Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    else{
        echo "<h2>Página no encontrada</h2>";
    }
    ?>
    </main>
</div>
</body>
</html>

