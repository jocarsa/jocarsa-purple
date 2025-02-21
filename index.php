<?php
session_start();

// Conexión a la base de datos SQLite (se creará el archivo si no existe)
$db = new PDO('sqlite:../databases/purple.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Crear tablas si no existen
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

// Opcional: Crear tabla para estudiantes
$db->exec("CREATE TABLE IF NOT EXISTS students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL,
    email TEXT
)");

// Insertar usuario inicial si no existe (usamos password_hash para mayor seguridad)
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute(['jocarsa']);
if ($stmt->fetchColumn() == 0) {
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute(['jocarsa', password_hash('jocarsa', PASSWORD_DEFAULT)]);
}

// Manejo de cierre de sesión
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: index.php");
    exit;
}

// Si el usuario no está logueado, mostrar login o registro
if(!isset($_SESSION['user'])){
    $page = isset($_GET['page']) ? $_GET['page'] : 'login';
    if($page == 'register'){
        // Registro
        if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['password'])){
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            if($stmt->fetchColumn() > 0){
                $error = "El usuario ya existe";
            } else {
                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt->execute([$_POST['username'], $hashedPassword]);
                header("Location: index.php");
                exit;
            }
        }
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Registro - jocarsa | purple</title>
            <link rel="stylesheet" href="style.css">
        </head>
        <body>
        <div class="register-container">
            <h1>Registro</h1>
            <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="post">
                <label>Usuario:</label>
                <input type="text" name="username" required>
                <label>Contraseña:</label>
                <input type="password" name="password" required>
                <input type="submit" value="Registrar">
            </form>
            <p>¿Ya tienes una cuenta? <a href="index.php">Inicia sesión aquí</a></p>
        </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        // Inicio de sesión
        if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['password'])){
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if($user && password_verify($_POST['password'], $user['password'])){
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
            <title>Iniciar sesión - jocarsa | purple</title>
            <link rel="stylesheet" href="style.css">
        </head>
        <body>
        <div class="login-container">
            <img src="purple.png" alt="Logo">
            <h1>jocarsa | purple</h1>
            <h2>Iniciar sesión</h2>
            <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="post">
                <label>Usuario:</label>
                <input type="text" name="username" required>
                <label>Contraseña:</label>
                <input type="password" name="password" required>
                <input type="submit" value="Entrar">
            </form>
            <p>¿No tienes cuenta? <a href="index.php?page=register">Regístrate aquí</a></p>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// El usuario ya está autenticado
$user_id = $_SESSION['user_id'];
$page   = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Panel de Administración - jocarsa | purple</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>jocarsa | purple</h1>
    <a href="?logout=1" class="logout">Cerrar sesión</a>
</header>
<div class="container">
    <nav>
        <h3>Información</h3>
        <ul>
            <li><a href="index.php?page=dashboard">Inicio</a></li>
            <li><a href="index.php?page=subjects">Asignaturas</a></li>
            <li><a href="index.php?page=teachers">Profesores</a></li>
            <li><a href="index.php?page=classrooms">Aulas</a></li>
            <li><a href="index.php?page=classes">Clases</a></li>
        </ul>
        <h3>Vistas</h3>
        <ul>
            <li><a href="index.php?page=calendar_month">Calendario Mensual</a></li>
            <li><a href="index.php?page=calendar_week">Calendario Semanal</a></li>
            <li><a href="index.php?page=grid">Vista Grilla</a></li>
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
                header("Location: index.php?page=subjects");
                exit;
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
                header("Location: index.php?page=subjects");
                exit;
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
            header("Location: index.php?page=subjects");
            exit;
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
                header("Location: index.php?page=teachers");
                exit;
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
                header("Location: index.php?page=teachers");
                exit;
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
            header("Location: index.php?page=teachers");
            exit;
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
                header("Location: index.php?page=classrooms");
                exit;
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
                header("Location: index.php?page=classrooms");
                exit;
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
            header("Location: index.php?page=classrooms");
            exit;
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
    
    // CRUD de Clases
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
                header("Location: index.php?page=classes");
                exit;
            } else {
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
                header("Location: index.php?page=classes");
                exit;
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
            header("Location: index.php?page=classes");
            exit;
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
    
    // Vista Calendario Mensual con controles de navegación
    elseif($page == 'calendar_month'){
        // Parámetros para navegar entre meses
        $year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $firstDayTimestamp = strtotime("$year-$month-01");
        $totalDays = date('t', $firstDayTimestamp);
        $startWeekday = date('N', $firstDayTimestamp); // lunes = 1

        // Calcular enlaces de navegación
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if($prevMonth < 1){
            $prevMonth = 12;
            $prevYear--;
        }
        $nextMonth = $month + 1;
        $nextYear  = $year;
        if($nextMonth > 12){
            $nextMonth = 1;
            $nextYear++;
        }

        // Consultar clases del mes seleccionado
        $startMonth = date("$year-$month-01 00:00:00");
        $endMonth   = date("$year-$month-$totalDays 23:59:59");
        $stmt = $db->prepare("SELECT * FROM classes WHERE user_id = ? AND start_time BETWEEN ? AND ?");
        $stmt->execute([$user_id, $startMonth, $endMonth]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $classesByDate = [];
        foreach($classes as $class){
            $date = date('Y-m-d', strtotime($class['start_time']));
            $classesByDate[$date][] = $class;
        }
        
        echo "<h2>Calendario Mensual</h2>";
        // Controles de navegación
        echo "<div class='controls'>";
        echo "<a href='index.php?page=calendar_month&year={$prevYear}&month={$prevMonth}'>&laquo; Mes Anterior</a>";
        echo "<span>" . date('F Y', $firstDayTimestamp) . "</span>";
        echo "<a href='index.php?page=calendar_month&year={$nextYear}&month={$nextMonth}'>Mes Siguiente &raquo;</a>";
        echo "</div>";

        echo "<table border='1' class='calendar'>";
        echo "<tr>
                <th>Lunes</th>
                <th>Martes</th>
                <th>Miércoles</th>
                <th>Jueves</th>
                <th>Viernes</th>
                <th>Sábado</th>
                <th>Domingo</th>
              </tr>";
        $day = 1;
        echo "<tr>";
        for($i = 1; $i < $startWeekday; $i++){
            echo "<td></td>";
        }
        for($i = $startWeekday; $i <= 7; $i++){
            $currentDate = "$year-" . str_pad($month,2,"0",STR_PAD_LEFT) . "-" . str_pad($day,2,"0",STR_PAD_LEFT);
            echo "<td><strong>$day</strong>";
            if(isset($classesByDate[$currentDate])){
                foreach($classesByDate[$currentDate] as $class){
                    echo "<div class='event'>Clase ID: " . htmlspecialchars($class['id']) . "</div>";
                }
            }
            echo "</td>";
            $day++;
        }
        echo "</tr>";
        while($day <= $totalDays){
            echo "<tr>";
            for($i = 1; $i <= 7; $i++){
                if($day <= $totalDays){
                    $currentDate = "$year-" . str_pad($month,2,"0",STR_PAD_LEFT) . "-" . str_pad($day,2,"0",STR_PAD_LEFT);
                    echo "<td><strong>$day</strong>";
                    if(isset($classesByDate[$currentDate])){
                        foreach($classesByDate[$currentDate] as $class){
                            echo "<div class='event'>Clase ID: " . htmlspecialchars($class['id']) . "</div>";
                        }
                    }
                    echo "</td>";
                } else {
                    echo "<td></td>";
                }
                $day++;
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vista Calendario Semanal con controles de navegación
    elseif($page == 'calendar_week'){
        // Parámetro week_offset para navegar semanas (0 = semana actual)
        $week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;
        $today = date('Y-m-d');
        $timestamp = strtotime($today);
        $dayOfWeek = date('N', $timestamp); // 1 (lunes) a 7 (domingo)
        // Calcular el lunes de la semana actual y aplicar offset
        $mondayTimestamp = strtotime("-" . ($dayOfWeek - 1) . " days", $timestamp);
        $mondayTimestamp = strtotime("{$week_offset} week", $mondayTimestamp);
        $monday = date('Y-m-d', $mondayTimestamp);
        $sunday = date('Y-m-d', strtotime("+6 days", $mondayTimestamp));

        // Enlaces de navegación
        echo "<h2>Calendario Semanal</h2>";
        echo "<div class='controls'>";
        echo "<a href='index.php?page=calendar_week&week_offset=" . ($week_offset - 1) . "'>&laquo; Semana Anterior</a>";
        echo "<span>Semana: " . date('d/m', $mondayTimestamp) . " - " . date('d/m', strtotime($sunday)) . "</span>";
        echo "<a href='index.php?page=calendar_week&week_offset=" . ($week_offset + 1) . "'>Semana Siguiente &raquo;</a>";
        echo "</div>";

        $startWeek = $monday . " 00:00:00";
        $endWeek   = $sunday . " 23:59:59";
        $stmt = $db->prepare("SELECT * FROM classes WHERE user_id = ? AND start_time BETWEEN ? AND ?");
        $stmt->execute([$user_id, $startWeek, $endWeek]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $classesByDate = [];
        for($i = 0; $i < 7; $i++){
            $date = date('Y-m-d', strtotime("+$i days", $mondayTimestamp));
            $classesByDate[$date] = [];
        }
        foreach($classes as $class){
            $date = date('Y-m-d', strtotime($class['start_time']));
            $classesByDate[$date][] = $class;
        }
        
        echo "<table border='1' class='calendar'>";
        echo "<tr>";
        for($i = 0; $i < 7; $i++){
            $date = date('Y-m-d', strtotime("+$i days", $mondayTimestamp));
            echo "<th>" . date('l', strtotime($date)) . "<br>" . date('d/m', strtotime($date)) . "</th>";
        }
        echo "</tr>";
        echo "<tr>";
        for($i = 0; $i < 7; $i++){
            $date = date('Y-m-d', strtotime("+$i days", $mondayTimestamp));
            echo "<td valign='top'>";
            if(!empty($classesByDate[$date])){
                foreach($classesByDate[$date] as $class){
                    echo "<div class='event'>";
                    echo "Clase ID: " . htmlspecialchars($class['id']) . "<br>";
                    echo "Inicio: " . htmlspecialchars($class['start_time']) . "<br>";
                    echo "Fin: " . htmlspecialchars($class['end_time']);
                    echo "</div>";
                }
            } else {
                echo "Sin clases";
            }
            echo "</td>";
        }
        echo "</tr>";
        echo "</table>";
    }
    
    // Vista Grilla: Horas vs. Días (Lunes a Viernes)
    elseif($page == 'grid'){
        echo "<h2>Vista Grilla (Horas vs. Días)</h2>";
        $startHour = 8;
        $endHour   = 18;
        $today = date('Y-m-d');
        $timestamp = strtotime($today);
        $dayOfWeek = date('N', $timestamp);
        $mondayTimestamp = strtotime("-" . ($dayOfWeek - 1) . " days", $timestamp);

        // Para esta vista, usaremos la semana actual (lunes a viernes)
        $monday = date('Y-m-d', $mondayTimestamp);
        $friday = date('Y-m-d', strtotime("+4 days", $mondayTimestamp));
        $startWeek = $monday . " 00:00:00";
        $endWeek   = $friday . " 23:59:59";
        $stmt = $db->prepare("SELECT * FROM classes WHERE user_id = ? AND start_time BETWEEN ? AND ?");
        $stmt->execute([$user_id, $startWeek, $endWeek]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $gridData = [];
        for($d = 0; $d < 5; $d++){
            $date = date('Y-m-d', strtotime("+$d days", $mondayTimestamp));
            for($h = $startHour; $h <= $endHour; $h++){
                $gridData[$date][$h] = [];
            }
        }
        foreach($classes as $class){
            $date = date('Y-m-d', strtotime($class['start_time']));
            $hour = date('G', strtotime($class['start_time']));
            if(isset($gridData[$date][$hour])){
                $gridData[$date][$hour][] = $class;
            }
        }
        
        echo "<table border='1' class='grid'>";
        echo "<tr><th>Hora</th>";
        for($d = 0; $d < 5; $d++){
            $date = date('Y-m-d', strtotime("+$d days", $mondayTimestamp));
            echo "<th>" . date('l d/m', strtotime($date)) . "</th>";
        }
        echo "</tr>";
        for($h = $startHour; $h <= $endHour; $h++){
            echo "<tr>";
            echo "<td><strong>" . str_pad($h, 2, '0', STR_PAD_LEFT) . ":00</strong></td>";
            for($d = 0; $d < 5; $d++){
                $date = date('Y-m-d', strtotime("+$d days", $mondayTimestamp));
                echo "<td valign='top'>";
                if(!empty($gridData[$date][$h])){
                    foreach($gridData[$date][$h] as $class){
                        echo "<div class='event'>";
                        echo "Clase ID: " . htmlspecialchars($class['id']) . "<br>";
                        echo "Fin: " . htmlspecialchars($class['end_time']);
                        echo "</div>";
                    }
                }
                echo "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    else{
        echo "<h2>Página no encontrada</h2>";
    }
    ?>
    </main>
</div>
</body>
</html>

