<?php
// modules/food/includes/api/api-recipes.php
require dirname(__DIR__, 4) . '/includes/auth.php';
require dirname(__DIR__, 4) . '/includes/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_recipe') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $title = trim($_POST['title'] ?? '');
            $image_url = trim($_POST['image_url'] ?? '');
            $prep_time = (int)($_POST['prep_time'] ?? 0);
            $cook_time = (int)($_POST['cook_time'] ?? 0);
            $rest_time = (int)($_POST['rest_time'] ?? 0);
            $base_servings = (int)($_POST['base_servings'] ?? 4);
            $difficulty = (int)($_POST['difficulty'] ?? 1);
            $cost = (int)($_POST['cost'] ?? 1);
            $chef_tip = trim($_POST['chef_tip'] ?? '');

            if (empty($title)) throw new Exception("Le titre est requis.");

            $pdo->beginTransaction();

            if ($id) {
                $stmt = $pdo->prepare("UPDATE pf_recipes SET title=?, image_url=?, prep_time=?, cook_time=?, rest_time=?, base_servings=?, difficulty=?, cost=?, chef_tip=? WHERE id=?");
                $stmt->execute([$title, $image_url, $prep_time, $cook_time, $rest_time, $base_servings, $difficulty, $cost, $chef_tip, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO pf_recipes (title, image_url, prep_time, cook_time, rest_time, base_servings, difficulty, cost, chef_tip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $image_url, $prep_time, $cook_time, $rest_time, $base_servings, $difficulty, $cost, $chef_tip]);
                $id = $pdo->lastInsertId();
            }

            // --- Sauvegarde des Ingrédients ---
            $pdo->prepare("DELETE FROM pf_recipe_ingredients WHERE recipe_id = ?")->execute([$id]);
            if (!empty($_POST['ing_name'])) {
                $stmtIng = $pdo->prepare("INSERT INTO pf_recipe_ingredients (recipe_id, name, quantity, unit, sort_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['ing_name'] as $idx => $ingName) {
                    if (trim($ingName) !== '') {
                        $qty = (float)($_POST['ing_qty'][$idx] ?? 0);
                        $unit = trim($_POST['ing_unit'][$idx] ?? '');
                        $stmtIng->execute([$id, trim($ingName), $qty, $unit, $idx]);
                    }
                }
            }

            // --- Sauvegarde des Étapes ---
            $pdo->prepare("DELETE FROM pf_recipe_steps WHERE recipe_id = ?")->execute([$id]);
            if (!empty($_POST['step_instruction'])) {
                $stmtStep = $pdo->prepare("INSERT INTO pf_recipe_steps (recipe_id, step_title, instruction, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($_POST['step_instruction'] as $idx => $instruction) {
                    if (trim($instruction) !== '') {
                        $stepTitle = trim($_POST['step_title'][$idx] ?? '');
                        $stmtStep->execute([$id, $stepTitle, trim($instruction), $idx]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete_recipe') {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM pf_recipes WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'toggle_favorite') {
            $id = (int)$_POST['id'];
            $state = (int)$_POST['state'];
            $pdo->prepare("UPDATE pf_recipes SET is_favorite = ? WHERE id = ?")->execute([$state, $id]);
            echo json_encode(['success' => true]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}