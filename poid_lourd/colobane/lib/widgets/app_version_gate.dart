import 'dart:io' show Platform;
import 'dart:math' as math;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_native_splash/flutter_native_splash.dart';
import 'package:url_launcher/url_launcher.dart';

import '../main.dart' show kBleuPrincipal, kBleuPrincipalFonce, kOrangePromo, kSplashLogoAsset;
import '../services/app_version_service.dart';

const Color _kTextDark = Color(0xFF0D0D0D);
const Color _kTextMuted = Color(0xFF737373);
const Color _kSurfaceSoft = Color(0xFFF5F8FC);

/// Vérifie la version au démarrage et bloque l'accès si une mise à jour est requise.
class AppVersionGate extends StatefulWidget {
  const AppVersionGate({super.key, required this.child});

  final Widget child;

  @override
  State<AppVersionGate> createState() => _AppVersionGateState();
}

class _AppVersionGateState extends State<AppVersionGate> {
  AppVersionCheckResult? _blockedResult;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _runCheck();
  }

  Future<void> _runCheck() async {
    final result = await fetchAppVersionCheck();
    if (!mounted) {
      return;
    }
    if (result != null && result.updateRequired) {
      FlutterNativeSplash.remove();
      setState(() {
        _blockedResult = result;
        _checking = false;
      });
      return;
    }
    if (mounted) {
      setState(() => _checking = false);
    }
  }

  void _clearBlock() {
    setState(() => _blockedResult = null);
  }

  @override
  Widget build(BuildContext context) {
    if (_blockedResult != null) {
      return ForceUpdateScreen(
        result: _blockedResult!,
        onUnblocked: _clearBlock,
      );
    }
    if (_checking) {
      return const _VersionCheckSplash();
    }
    return widget.child;
  }
}

class _VersionCheckSplash extends StatelessWidget {
  const _VersionCheckSplash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: SizedBox(
          width: 120,
          height: 120,
          child: Image(
            image: AssetImage(kSplashLogoAsset),
            fit: BoxFit.contain,
          ),
        ),
      ),
    );
  }
}

class ForceUpdateScreen extends StatefulWidget {
  const ForceUpdateScreen({
    super.key,
    required this.result,
    required this.onUnblocked,
  });

  final AppVersionCheckResult result;
  final VoidCallback onUnblocked;

  @override
  State<ForceUpdateScreen> createState() => _ForceUpdateScreenState();
}

class _ForceUpdateScreenState extends State<ForceUpdateScreen>
    with SingleTickerProviderStateMixin {
  bool _rechecking = false;
  late final AnimationController _anim;
  late final Animation<double> _fade;
  late final Animation<Offset> _slide;

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 680),
    );
    _fade = CurvedAnimation(parent: _anim, curve: Curves.easeOutCubic);
    _slide = Tween<Offset>(
      begin: const Offset(0, 0.06),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _anim, curve: Curves.easeOutCubic));
    _anim.forward();
  }

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  bool get _isIos => !kIsWeb && Platform.isIOS;

  Future<void> _openStore() async {
    final url = Uri.tryParse(widget.result.storeUrl);
    if (url == null) {
      return;
    }
    await launchUrl(url, mode: LaunchMode.externalApplication);
  }

  Future<void> _runRecheck() async {
    if (_rechecking) {
      return;
    }
    setState(() => _rechecking = true);
    final fresh = await fetchAppVersionCheck();
    if (!mounted) {
      return;
    }
    setState(() => _rechecking = false);
    if (fresh != null && !fresh.updateRequired) {
      widget.onUnblocked();
    }
  }

  @override
  Widget build(BuildContext context) {
    final result = widget.result;
    final size = MediaQuery.sizeOf(context);
    final isCompact = size.height < 640;
    final horizontalPad = size.width < 380 ? 16.0 : 24.0;
    final cardMaxWidth = math.min(440.0, size.width - horizontalPad * 2);

    return PopScope(
      canPop: false,
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.light.copyWith(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
        ),
        child: Scaffold(
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _UpdateBackdrop(),
              SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return SingleChildScrollView(
                      padding: EdgeInsets.symmetric(
                        horizontal: horizontalPad,
                        vertical: isCompact ? 16 : 28,
                      ),
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          minHeight: constraints.maxHeight -
                              (isCompact ? 32 : 56),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            FadeTransition(
                              opacity: _fade,
                              child: SlideTransition(
                                position: _slide,
                                child: Container(
                                  width: cardMaxWidth,
                                  constraints: BoxConstraints(
                                    maxWidth: size.width,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(28),
                                    boxShadow: [
                                      BoxShadow(
                                        color: kBleuPrincipal.withValues(
                                          alpha: 0.18,
                                        ),
                                        blurRadius: 40,
                                        offset: const Offset(0, 18),
                                      ),
                                      BoxShadow(
                                        color: Colors.black.withValues(
                                          alpha: 0.06,
                                        ),
                                        blurRadius: 12,
                                        offset: const Offset(0, 4),
                                      ),
                                    ],
                                    border: Border.all(
                                      color: Colors.white.withValues(
                                        alpha: 0.9,
                                      ),
                                    ),
                                  ),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(28),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        _UpdateHeroHeader(isIos: _isIos),
                                        Padding(
                                          padding: EdgeInsets.fromLTRB(
                                            isCompact ? 20 : 28,
                                            0,
                                            isCompact ? 20 : 28,
                                            isCompact ? 22 : 28,
                                          ),
                                          child: Column(
                                            children: [
                                              Text(
                                                result.title.isNotEmpty
                                                    ? result.title
                                                    : 'Mise à jour requise',
                                                textAlign: TextAlign.center,
                                                style: TextStyle(
                                                  fontSize:
                                                      isCompact ? 21 : 24,
                                                  fontWeight: FontWeight.w800,
                                                  height: 1.2,
                                                  color: _kTextDark,
                                                  letterSpacing: -0.3,
                                                ),
                                              ),
                                              const SizedBox(height: 12),
                                              Text(
                                                result.message.isNotEmpty
                                                    ? result.message
                                                    : 'Veuillez mettre à jour l\'application pour continuer.',
                                                textAlign: TextAlign.center,
                                                style: TextStyle(
                                                  fontSize:
                                                      isCompact ? 14 : 15,
                                                  height: 1.55,
                                                  color: const Color(
                                                    0xFF4A4A4A,
                                                  ),
                                                ),
                                              ),
                                              if (result.minBuild > 0) ...[
                                                const SizedBox(height: 18),
                                                _VersionCompareRow(
                                                  installed:
                                                      result.installedBuild,
                                                  required: result.minBuild,
                                                ),
                                              ],
                                              const SizedBox(height: 24),
                                              _PrimaryUpdateButton(
                                                isIos: _isIos,
                                                onPressed: _openStore,
                                              ),
                                              const SizedBox(height: 10),
                                              TextButton.icon(
                                                onPressed: _rechecking
                                                    ? null
                                                    : _runRecheck,
                                                icon: _rechecking
                                                    ? SizedBox(
                                                        width: 16,
                                                        height: 16,
                                                        child:
                                                            CircularProgressIndicator(
                                                          strokeWidth: 2,
                                                          color: kBleuPrincipal
                                                              .withValues(
                                                            alpha: 0.7,
                                                          ),
                                                        ),
                                                      )
                                                    : const Icon(
                                                        Icons.refresh_rounded,
                                                        size: 18,
                                                      ),
                                                label: Text(
                                                  _rechecking
                                                      ? 'Vérification…'
                                                      : 'J\'ai déjà mis à jour',
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.w600,
                                                  ),
                                                ),
                                                style: TextButton.styleFrom(
                                                  foregroundColor:
                                                      kBleuPrincipal,
                                                  padding: const EdgeInsets
                                                      .symmetric(
                                                    horizontal: 12,
                                                    vertical: 10,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            SizedBox(height: isCompact ? 16 : 24),
                            FadeTransition(
                              opacity: _fade,
                              child: Text(
                                'COLObanes — marketplace',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: Colors.white.withValues(alpha: 0.75),
                                  letterSpacing: 0.4,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _UpdateBackdrop extends StatelessWidget {
  const _UpdateBackdrop();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            kBleuPrincipalFonce,
            kBleuPrincipal,
            const Color(0xFF4A7AB8),
            kBleuPrincipalFonce.withValues(alpha: 0.95),
          ],
          stops: const [0.0, 0.35, 0.72, 1.0],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -80,
            right: -60,
            child: _GlowOrb(
              size: 220,
              color: kOrangePromo.withValues(alpha: 0.22),
            ),
          ),
          Positioned(
            bottom: -100,
            left: -80,
            child: _GlowOrb(
              size: 260,
              color: Colors.white.withValues(alpha: 0.08),
            ),
          ),
          Positioned(
            top: MediaQuery.sizeOf(context).height * 0.35,
            left: -40,
            child: _GlowOrb(
              size: 120,
              color: Colors.white.withValues(alpha: 0.06),
            ),
          ),
        ],
      ),
    );
  }
}

class _GlowOrb extends StatelessWidget {
  const _GlowOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color,
        boxShadow: [
          BoxShadow(
            color: color,
            blurRadius: size * 0.35,
            spreadRadius: size * 0.05,
          ),
        ],
      ),
    );
  }
}

class _UpdateHeroHeader extends StatelessWidget {
  const _UpdateHeroHeader({required this.isIos});

  final bool isIos;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            _kSurfaceSoft,
            Colors.white,
          ],
        ),
      ),
      child: Column(
        children: [
          Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.center,
            children: [
              Container(
                width: 96,
                height: 96,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: kBleuPrincipal.withValues(alpha: 0.15),
                      blurRadius: 24,
                      offset: const Offset(0, 8),
                    ),
                  ],
                  border: Border.all(
                    color: kBleuPrincipal.withValues(alpha: 0.08),
                    width: 1.5,
                  ),
                ),
                padding: const EdgeInsets.all(14),
                child: Image.asset(
                  kSplashLogoAsset,
                  fit: BoxFit.contain,
                ),
              ),
              Positioned(
                right: -4,
                bottom: -4,
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [kOrangePromo, const Color(0xFFE85A2A)],
                    ),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2.5),
                    boxShadow: [
                      BoxShadow(
                        color: kOrangePromo.withValues(alpha: 0.35),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.system_update_rounded,
                    color: Colors.white,
                    size: 18,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: kBleuPrincipal.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color: kBleuPrincipal.withValues(alpha: 0.15),
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isIos ? Icons.apple : Icons.android,
                  size: 15,
                  color: kBleuPrincipal,
                ),
                const SizedBox(width: 6),
                Text(
                  isIos ? 'Mise à jour App Store' : 'Mise à jour Google Play',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: kBleuPrincipal,
                    letterSpacing: 0.2,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VersionCompareRow extends StatelessWidget {
  const _VersionCompareRow({
    required this.installed,
    required this.required,
  });

  final int installed;
  final int required;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: _kSurfaceSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: kBleuPrincipal.withValues(alpha: 0.1),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: _VersionChip(
              label: 'Installée',
              value: 'build $installed',
              accent: _kTextMuted,
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Icon(
              Icons.arrow_forward_rounded,
              size: 18,
              color: kOrangePromo.withValues(alpha: 0.85),
            ),
          ),
          Expanded(
            child: _VersionChip(
              label: 'Requise',
              value: 'build $required',
              accent: kBleuPrincipal,
              highlight: true,
            ),
          ),
        ],
      ),
    );
  }
}

class _VersionChip extends StatelessWidget {
  const _VersionChip({
    required this.label,
    required this.value,
    required this.accent,
    this.highlight = false,
  });

  final String label;
  final String value;
  final Color accent;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: _kTextMuted,
            letterSpacing: 0.3,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            color: highlight ? accent : _kTextDark,
          ),
        ),
      ],
    );
  }
}

class _PrimaryUpdateButton extends StatelessWidget {
  const _PrimaryUpdateButton({
    required this.isIos,
    required this.onPressed,
  });

  final bool isIos;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [kBleuPrincipal, kBleuPrincipalFonce],
          ),
          boxShadow: [
            BoxShadow(
              color: kBleuPrincipal.withValues(alpha: 0.38),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onPressed,
            borderRadius: BorderRadius.circular(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  isIos ? Icons.apple : Icons.shop_rounded,
                  color: Colors.white,
                  size: 20,
                ),
                const SizedBox(width: 10),
                const Text(
                  'Mettre à jour maintenant',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.1,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
