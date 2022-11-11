import {definitionForModuleAndIdentifier, identifierForContextKey} from '@hotwired/stimulus-webpack-helpers'
import { startStimulusApp } from '@symfony/stimulus-bridge';
import { Autocomplete } from '@symfony/stimulus-bridge/lazy-controller-loader?lazy=true&export=Autocomplete!stimulus-autocomplete';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.(j|t)sx?$/
));
app.register('autocomplete', Autocomplete);

const context = require.context('./controllers', true, /\.js$/);

// Register all controllers with `contao--` prefix.
app.load(context.keys().map((key) => {
    const identifier = identifierForContextKey(key);
    if (identifier) {
        return definitionForModuleAndIdentifier(context(key), `contao--${identifier}`);
    }
}).filter((value) => value));

// //import './turbo/turbo-helper';
// //import './turbo/prefetch';
