import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './style.css';

registerBlockType('openalex/publications-selector', {
    edit: Edit,
    save: () => null // Renderizado dinámico en PHP
});
