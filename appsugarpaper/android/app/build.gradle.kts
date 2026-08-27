import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    // Plugin Google Services pour Firebase
    id("com.google.gms.google-services")
}

android {
    namespace = "com.sugarpaper.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        isCoreLibraryDesugaringEnabled = true
    }

    defaultConfig {
        // Identifiant unique Sugar Paper
        applicationId = "com.sugarpaper.app"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            val keystorePropertiesFile = rootProject.file("key.properties")
            if (keystorePropertiesFile.exists()) {
                val keystoreProperties = Properties()
                keystoreProperties.load(FileInputStream(keystorePropertiesFile))
                storeFile = file(keystoreProperties.getProperty("storeFile") ?: "")
                storePassword = keystoreProperties.getProperty("storePassword") ?: ""
                keyAlias = keystoreProperties.getProperty("keyAlias") ?: ""
                keyPassword = keystoreProperties.getProperty("keyPassword") ?: ""
            }
        }
    }

    buildTypes {
        release {
            // Minification et obfuscation pour la production
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Utiliser la configuration de signature release si key.properties existe
            if (rootProject.file("key.properties").exists()) {
                signingConfig = signingConfigs.getByName("release")
            } else {
                // Fallback sur debug si key.properties n'existe pas encore
                signingConfig = signingConfigs.getByName("debug")
            }
        }
    }
    
    // Personnaliser le nom du fichier APK
    applicationVariants.all {
        val variant = this
        variant.outputs.all {
            val output = this as com.android.build.gradle.internal.api.BaseVariantOutputImpl
            // Ne renommer que les APK (les AAB sont gérés par la tâche ci-dessous)
            if (output.outputFile?.extension == "apk") {
                val outputFileName = when {
                    variant.buildType.name == "release" -> "SUGAR_PAPER_APP.apk"
                    else -> "SUGAR_PAPER_APP-debug.apk"
                }
                output.outputFileName = outputFileName
            }
        }
    }
    
    // Configuration spécifique pour les AAB (Android App Bundle)
    bundle {
        language {
            enableSplit = false
        }
        // Segmentation par densité : chaque appareil ne télécharge que les images
        // adaptées à sa densité d'écran (recommandation Google Play Console).
        density {
            enableSplit = true
        }
        abi {
            enableSplit = true
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

// Tâche pour renommer le fichier AAB après génération
afterEvaluate {
    val buildOutputDir = layout.buildDirectory.get().asFile
    // Pour la version release
    tasks.findByName("bundleRelease")?.let { bundleTask ->
        val renameTask = tasks.register("renameReleaseAAB") {
            doLast {
                val bundleDir = file("${buildOutputDir}/outputs/bundle/release")
                val originalFile = file("${bundleDir}/app-release.aab")
                val renamedFile = file("${bundleDir}/SUGAR_PAPER_APP.aab")
                
                if (originalFile.exists()) {
                    if (renamedFile.exists()) {
                        renamedFile.delete()
                    }
                    originalFile.renameTo(renamedFile)
                    println("✅ Fichier AAB renommé: ${originalFile.name} -> ${renamedFile.name}")
                } else {
                    println("⚠️  Fichier AAB non trouvé: ${originalFile.absolutePath}")
                }
            }
        }
        renameTask.configure { dependsOn(bundleTask) }
        bundleTask.finalizedBy(renameTask)
    }
    
    // Pour la version debug
    tasks.findByName("bundleDebug")?.let { bundleTask ->
        val renameTask = tasks.register("renameDebugAAB") {
            doLast {
                val bundleDir = file("${buildOutputDir}/outputs/bundle/debug")
                val originalFile = file("${bundleDir}/app-debug.aab")
                val renamedFile = file("${bundleDir}/SUGAR_PAPER_APP-debug.aab")
                
                if (originalFile.exists()) {
                    if (renamedFile.exists()) {
                        renamedFile.delete()
                    }
                    originalFile.renameTo(renamedFile)
                    println("✅ Fichier AAB renommé: ${originalFile.name} -> ${renamedFile.name}")
                } else {
                    println("⚠️  Fichier AAB non trouvé: ${originalFile.absolutePath}")
                }
            }
        }
        renameTask.configure { dependsOn(bundleTask) }
        bundleTask.finalizedBy(renameTask)
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.0.4")

    // Firebase (versions alignées sur la BoM — ne pas fixer les versions des libs Firebase une par une)
    implementation(platform("com.google.firebase:firebase-bom:34.15.0"))
    implementation("com.google.firebase:firebase-analytics")
    implementation("com.google.firebase:firebase-auth")
    implementation("com.google.firebase:firebase-messaging")
    
    // Note: Google Play Core a été supprimé car incompatible avec SDK 34 (Android 14)
    // Ces dépendances n'étaient pas nécessaires pour le fonctionnement de l'application
}
