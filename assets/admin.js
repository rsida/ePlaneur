/*
 * Back-office entry point (EasyAdmin pages, see DashboardController::configureAssets()): the block
 * editor of posts and pages and the page tree. Turbo and the site controllers are not loaded here.
 */
import { Application } from '@hotwired/stimulus';
// Stateless CSRF tokens of the Symfony forms (double-submit cookie), as on the site
import './controllers/csrf_protection_controller.js';
import BlockEditorController from './editor/block_editor_controller.js';
import PageTreeController from './editor/page_tree_controller.js';
import './styles/editor.css';

const application = Application.start();
application.register('block-editor', BlockEditorController);
application.register('page-tree', PageTreeController);
