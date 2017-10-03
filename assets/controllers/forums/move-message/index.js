import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import FORUM_SERVICE_NAME from 'services/forum';

const CONTROLLER_NAME = 'ForumsMoveMessageController';
const STATE_NAME = 'forums-move-message';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/move-message?message_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                pageId: 83
            });
            
            var ctrl = this;
            
            ctrl.message_id = $state.params.message_id;
            ctrl.themes = [];
            ctrl.theme = null;
            ctrl.topics = [];
            
            $http({
                url: '/api/forum/themes',
                method: 'GET'
            }).then(function(response) {
                
                ctrl.themes = response.data.items;
                
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.selectTheme = function(theme) {
                ctrl.theme = theme;
                $http({
                    url: '/api/forum/topic',
                    method: 'GET',
                    params: {
                        theme_id: theme.id
                    }
                }).then(function(response) {
                    
                    ctrl.topics = response.data.items;
                    
                }, function(response) {
                    notify.response(response);
                });
            };

            ctrl.selectTopic = function(topic) {
                $http({
                    method: 'PUT',
                    url: '/api/comment/' + ctrl.message_id,
                    data: {
                        item_id: topic.id
                    }
                }).then(function(response) {
                    
                    Forum.getMessageStateParams(ctrl.message_id).then(function(params) {
                        $state.go('forums-topic', params);
                    }, function(response) {
                        notify.response(response);
                    });
                    
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
