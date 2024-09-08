import ace from 'ace-builds';
import 'ace-builds/esm-resolver';
import codeLens from 'ace-builds/src-noconflict/ext-code_lens'
import languageTools from 'ace-builds/src-noconflict/ext-language_tools'
import whitespace from 'ace-builds/src-noconflict/ext-whitespace'

export class TwigEditor {
    constructor(element) {
        this.name = element.dataset.name;
        this.resourceUrl = element.dataset.resourceUrl;

        this.editor = ace.edit(element, {
            theme: document.documentElement.dataset.colorScheme === 'dark' ? 'ace/theme/twilight' : 'ace/theme/clouds',
            mode: 'ace/mode/twig',
            maxLines: 100,
            wrap: true,
            useSoftTabs: false,
            autoScrollEditorIntoView: true,
            readOnly: element.hasAttribute('readonly'),
        });

        this.editor.container.style.lineHeight = '1.45';
        whitespace.detectIndentation(this.editor.getSession());

        this.editor.commands.addCommand({
            name: 'save',
            bindKey: {win: 'Ctrl-S', mac: 'Command-S'},
            exec: (editor, args) => {
               if(editor.getSession().getUndoManager().isClean()) {
                   return;
               }

                editor.container.dispatchEvent(
                   new CustomEvent('save', {
                       bubbles: true,
                       detail: {
                           resourceUrl: this.resourceUrl,
                           content: editor.getValue(),
                       }
                   })
               );
            },
        });

        this.editor.commands.addCommand({
            name: 'showBlockInfo',
            readOnly: true,
            exec: (editor, args) => {
               editor.container.dispatchEvent(
                   new CustomEvent('block-info', {
                       bubbles: true,
                       detail: {
                           name: this.name,
                           block: args[0],
                       }
                   })
               );
            },
        });

        this.editor.commands.addCommand({
            name: 'open',
            readOnly: true,
            exec: (editor, args) => {
                editor.container.dispatchEvent(
                    new CustomEvent('open', {
                        bubbles: true,
                        detail: {
                            name: args[0]
                        }
                    })
                );
            },
        });

        this.editor.getSession().once('tokenizerUpdate', () => {
            codeLens.registerCodeLensProvider(this.editor, {
                provideCodeLenses: (session, callback) => {
                    let payload = [];

                    this.analyzeBlocks().forEach(block => {
                        payload.push({
                            start: {row: block.row, column: block.column},
                            command: {
                                id: 'showBlockInfo',
                                title: `Block "${block.name}"`,
                                arguments: [block.name]
                            }
                        })
                    });

                    this.analyzeReferences().forEach(reference => {
                        payload.push({
                            start: {row: reference.row, column: reference.column},
                            command: {
                                id: 'open',
                                title: reference.name,
                                arguments: [reference.name]
                            }
                        })
                    });

                    callback(null, payload)
                }
            })
        })
    }

    analyzeBlocks() {
        let blocks = [];

        for(let row = 0; row < this.editor.getSession().getLength(); row++) {
            const tokens = this.editor.getSession().getTokens(row);

            for (let i = 0; i < tokens.length; i++) {
                if (tokens[i].type === 'meta.tag.twig' &&
                    /^{%-?$/.test(tokens[i].value) &&
                    tokens[i + 2]?.type === 'keyword.control.twig' &&
                    tokens[i + 2].value === 'block' &&
                    tokens[i + 4]?.type === 'identifier'
                ) {
                    blocks.push({name: tokens[i + 4].value, row, column: tokens[i].start});
                }
            }
        }

        return blocks;
    }

    analyzeReferences() {
        let references = [];

        for(let row = 0; row < this.editor.getSession().getLength(); row++) {
            const tokens = this.editor.getSession().getTokens(row);

            for (let i = 0; i < tokens.length; i++) {
                if (tokens[i].type === 'meta.tag.twig' &&
                    /^{%-?$/.test(tokens[i].value) &&
                    tokens[i + 2]?.type === 'keyword.control.twig' &&
                    ['extends', 'use'].includes(tokens[i+2].value) &&
                    tokens[i + 4]?.type === 'string'
                ) {
                    const name = tokens[i+4].value.replace(/["']/g, '');

                    if(name.test(/^@Contao(_.+)?\//)) {
                        references.push({name, row, column: tokens[i].start});
                    }
                }
            }
        }

        return references;
    }

    destroy() {
        this.editor.destroy();
    }
}
