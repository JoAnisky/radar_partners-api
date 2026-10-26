pipeline {
    agent none

    environment {
        APP_NAME = "radarpartners-api"
        DOCKER_IMAGE = "joanisky/radarpartners-api"
        DOCKER_TAG = "${BUILD_NUMBER}"
        KUBE_NAMESPACE = "radarpartners-api"
        KUBE_DEPLOYMENT = "radar-api"
    }

    stages {
        stage('Checkout') {
            agent any
            steps {
                checkout scm
                script {
                    env.GIT_BRANCH_NAME = env.GIT_BRANCH ?: scm.branches[0].name
                    echo "Build: ${BUILD_NUMBER} — Branche: ${env.GIT_BRANCH_NAME}"
                }
            }
        }
        stage('Tests') {
            agent any
            steps {
                sh '''
                    NET=ci-radar-api-${BUILD_NUMBER}
                    docker network create $NET
                    docker run -d --name $NET-db --network $NET --network-alias db \
                        -e POSTGRES_USER=radar -e POSTGRES_PASSWORD=radar -e POSTGRES_DB=radar \
                        postgres:17-alpine

                    docker build -f .docker/Dockerfile --target test -t ${APP_NAME}-test:${BUILD_NUMBER} .

                    docker run --rm --network $NET \
                        -e DATABASE_URL="postgresql://radar:radar@db:5432/radar?serverVersion=17&charset=utf8" \
                        ${APP_NAME}-test:${BUILD_NUMBER} /bin/bash -c '
                            set -e
                            for i in $(seq 1 30); do
                                (echo > /dev/tcp/db/5432) 2>/dev/null && break
                                sleep 2
                            done
                            php bin/console doctrine:database:create --env=test --if-not-exists --no-interaction
                            php bin/console doctrine:migrations:migrate --env=test --no-interaction
                            vendor/bin/phpunit --testdox
                        '
                '''
            }
            post {
                always {
                    sh '''
                        NET=ci-radar-api-${BUILD_NUMBER}
                        docker rm -f $NET-db || true
                        docker network rm $NET || true
                        docker rmi ${APP_NAME}-test:${BUILD_NUMBER} || true
                    '''
                }
            }
        }
        stage('Build & Push Docker Image') {
            agent any
            steps {
                withCredentials([
                    usernamePassword(credentialsId: 'jenkins-dockerhub', usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')
                ]) {
                    sh '''
                        docker login -u $DOCKER_USER -p $DOCKER_PASS

                        docker build \
                            -f .docker/Dockerfile \
                            --target prod \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG} .

                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG}

                        docker tag ${DOCKER_IMAGE}:${DOCKER_TAG} ${DOCKER_IMAGE}:latest
                        docker push ${DOCKER_IMAGE}:latest
                    '''
                }
            }
        }
        stage('Deploy Secrets') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                    string(credentialsId: 'radar-app-secret', variable: 'APP_SECRET'),
                    string(credentialsId: 'radar-db-user', variable: 'DB_USER'),
                    string(credentialsId: 'radar-db-password', variable: 'DB_PASS')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        kubectl create namespace ${KUBE_NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -

                        kubectl create secret generic radar-db-secret \
                            --from-literal=user=${DB_USER} \
                            --from-literal=password=${DB_PASS} \
                            -n ${KUBE_NAMESPACE} \
                            --dry-run=client -o yaml | kubectl apply -f -

                        kubectl create secret generic radar-api-env \
                            --from-literal=APP_SECRET=${APP_SECRET} \
                            --from-literal=DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@radar-db:5432/radar?serverVersion=17&charset=utf8" \
                            -n ${KUBE_NAMESPACE} \
                            --dry-run=client -o yaml | kubectl apply -f -
                    '''
                }
            }
        }
        stage('Deploy to Kubernetes') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        echo "Application des manifests Kubernetes..."
                        kubectl apply -k k8s/

                        echo "Attente de PostgreSQL..."
                        kubectl rollout status deployment/radar-db -n ${KUBE_NAMESPACE} --timeout=180s

                        echo "Migrations..."
                        kubectl delete job radar-db-migrate -n ${KUBE_NAMESPACE} --ignore-not-found
                        sed "s|joanisky/radarpartners-api:latest|${DOCKER_IMAGE}:${DOCKER_TAG}|" k8s/jobs/job-migrate.yaml | kubectl apply -f -
                        kubectl wait --for=condition=complete job/radar-db-migrate -n ${KUBE_NAMESPACE} --timeout=300s \
                            || { kubectl logs job/radar-db-migrate -n ${KUBE_NAMESPACE}; exit 1; }

                        echo "Mise a jour de l image vers: ${DOCKER_TAG}"
                        kubectl set image deployment/${KUBE_DEPLOYMENT} app=${DOCKER_IMAGE}:${DOCKER_TAG} -n ${KUBE_NAMESPACE}
                        kubectl set image cronjob/radar-demo-reset seed=${DOCKER_IMAGE}:${DOCKER_TAG} -n ${KUBE_NAMESPACE}

                        echo "Attente du rollout..."
                        kubectl rollout status deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} --timeout=300s
                    '''
                }
            }
        }
        stage('Seed Demo Data') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        kubectl delete job radar-demo-seed -n ${KUBE_NAMESPACE} --ignore-not-found
                        kubectl create job radar-demo-seed --from=cronjob/radar-demo-reset -n ${KUBE_NAMESPACE}
                        kubectl wait --for=condition=complete job/radar-demo-seed -n ${KUBE_NAMESPACE} --timeout=300s \
                            || { kubectl logs job/radar-demo-seed -n ${KUBE_NAMESPACE}; exit 1; }
                    '''
                }
            }
        }
        stage('Verify Deployment') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        kubectl get pods -n ${KUBE_NAMESPACE}
                        kubectl get svc -n ${KUBE_NAMESPACE}
                        kubectl get ingress -n ${KUBE_NAMESPACE}
                        kubectl get cronjob -n ${KUBE_NAMESPACE}
                    '''
                }
            }
        }
        stage('Health Check') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    timeout(time: 3, unit: 'MINUTES') {
                        waitUntil {
                            script {
                                def response = sh(
                                    script: 'curl -s -o /dev/null -w "%{http_code}" https://api.radar.jonathanlore.fr/api/health || true',
                                    returnStdout: true
                                ).trim()

                                if (response == '200') {
                                    echo "API accessible"
                                    return true
                                } else {
                                    echo "En attente... (${response})"
                                    sleep 5
                                    return false
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    post {
        always {
            node('built-in') {
                sh "docker rmi ${DOCKER_IMAGE}:${DOCKER_TAG} || true"
                sh "docker rmi ${DOCKER_IMAGE}:latest || true"
            }
        }
        success {
            withCredentials([string(credentialsId: 'discord-webhook-url', variable: 'DISCORD_URL')]) {
                discordSend(
                    webhookURL: DISCORD_URL,
                    title: "Deploiement Reussi : ${APP_NAME}",
                    link: env.BUILD_URL,
                    result: 'SUCCESS',
                    description: "Le build #${env.BUILD_NUMBER} a ete deploye avec succes sur Kubernetes.\n**Branche:** ${env.GIT_BRANCH_NAME}"
                )
            }
        }
        failure {
            withCredentials([string(credentialsId: 'discord-webhook-url', variable: 'DISCORD_URL')]) {
                discordSend(
                    webhookURL: DISCORD_URL,
                    title: "Echec du Pipeline : ${APP_NAME}",
                    link: env.BUILD_URL,
                    result: 'FAILURE',
                    description: "Le build #${env.BUILD_NUMBER} a echoue.\nConsulte les logs ici : ${env.BUILD_URL}console"
                )
            }
        }
    }
}
