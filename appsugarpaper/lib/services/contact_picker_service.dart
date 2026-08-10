import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';

import '../theme/app_colors.dart';
import 'native_permission_service.dart';

/// Sélection multi-contacts native (iOS / Android) pour import carnet clients.
class ContactPickerService {
  static Map<String, String> _splitName(String full) {
    final cleaned = full.trim().replaceAll(RegExp(r'\s+'), ' ');
    if (cleaned.isEmpty) {
      return {'nom': 'Sans nom', 'prenom': ''};
    }
    final parts = cleaned.split(' ');
    if (parts.length == 1) {
      return {'nom': parts.first, 'prenom': ''};
    }
    return {
      'prenom': parts.first,
      'nom': parts.sublist(1).join(' '),
    };
  }

  static String _firstPhone(Contact contact) {
    if (contact.phones.isEmpty) return '';
    return contact.phones.first.number.trim();
  }

  static String _firstEmail(Contact contact) {
    if (contact.emails.isEmpty) return '';
    return contact.emails.first.address.trim();
  }

  static Map<String, dynamic> _toImportRow(Contact contact) {
    final display = contact.displayName.trim();
    final given = contact.name.first.trim();
    final family = contact.name.last.trim();
    String nom;
    String prenom;
    if (family.isNotEmpty || given.isNotEmpty) {
      nom = family.isNotEmpty ? family : (display.isNotEmpty ? display : 'Sans nom');
      prenom = given;
      if (nom == prenom && family.isEmpty) {
        final split = _splitName(display.isNotEmpty ? display : nom);
        nom = split['nom']!;
        prenom = split['prenom']!;
      }
    } else {
      final split = _splitName(display);
      nom = split['nom']!;
      prenom = split['prenom']!;
    }
    return {
      'nom': nom,
      'prenom': prenom,
      'telephone': _firstPhone(contact),
      'email': _firstEmail(contact),
    };
  }

  /// Demande permission + UI de sélection → liste `{nom,prenom,telephone,email}`.
  static Future<Map<String, dynamic>> pickContacts(BuildContext context) async {
    try {
      final allowed =
          await NativePermissionService.requestContactsWithRationale(context);
      if (!allowed) {
        return {
          'success': false,
          'error': 'Permission contacts refusée',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      // flutter_contacts : s'assurer que le plugin a aussi la permission
      final pluginOk = await FlutterContacts.requestPermission(readonly: true);
      if (!pluginOk) {
        return {
          'success': false,
          'error': 'Permission contacts refusée',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      final all = await FlutterContacts.getContacts(
        withProperties: true,
        withPhoto: false,
      );
      final withPhone = all
          .where((c) => c.phones.any((p) => p.number.trim().isNotEmpty))
          .toList()
        ..sort(
          (a, b) => a.displayName.toLowerCase().compareTo(
                b.displayName.toLowerCase(),
              ),
        );

      if (withPhone.isEmpty) {
        return {
          'success': false,
          'error': 'Aucun contact avec numéro de téléphone',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      if (!context.mounted) {
        return {
          'success': false,
          'error': 'Application non prête',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      final selected = await showModalBottomSheet<List<Contact>>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
        ),
        builder: (ctx) => _ContactMultiSelectSheet(contacts: withPhone),
      );

      if (selected == null) {
        return {
          'success': false,
          'error': 'Import annulé',
          'contacts': <Map<String, dynamic>>[],
          'cancelled': true,
        };
      }

      final rows = selected
          .map(_toImportRow)
          .where((r) => (r['telephone'] as String).isNotEmpty)
          .toList();

      if (rows.isEmpty) {
        return {
          'success': false,
          'error': 'Aucun contact sélectionné avec téléphone',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      return {
        'success': true,
        'contacts': rows,
        'count': rows.length,
      };
    } catch (e) {
      return {
        'success': false,
        'error': e.toString(),
        'contacts': <Map<String, dynamic>>[],
      };
    }
  }

  /// Charge le carnet (permission + lecture) sans UI de sélection —
  /// pour suggestions live dans la recherche client (BL / devis).
  static Future<Map<String, dynamic>> getDeviceContacts(
    BuildContext context,
  ) async {
    try {
      final allowed =
          await NativePermissionService.requestContactsWithRationale(context);
      if (!allowed) {
        return {
          'success': false,
          'error': 'Permission contacts refusée',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      final pluginOk = await FlutterContacts.requestPermission(readonly: true);
      if (!pluginOk) {
        return {
          'success': false,
          'error': 'Permission contacts refusée',
          'contacts': <Map<String, dynamic>>[],
        };
      }

      final all = await FlutterContacts.getContacts(
        withProperties: true,
        withPhoto: false,
      );

      final rows = <Map<String, dynamic>>[];
      for (final c in all) {
        final phone = _firstPhone(c);
        if (phone.isEmpty) continue;
        final row = _toImportRow(c);
        final display = c.displayName.trim();
        final nomComplet = display.isNotEmpty
            ? display
            : '${row['prenom']} ${row['nom']}'.trim();
        rows.add({
          'nom': row['nom'],
          'prenom': row['prenom'],
          'nom_complet': nomComplet.isNotEmpty ? nomComplet : 'Sans nom',
          'telephone': phone,
          'email': row['email'],
          'source': 'device',
        });
      }

      rows.sort(
        (a, b) => (a['nom_complet'] as String)
            .toLowerCase()
            .compareTo((b['nom_complet'] as String).toLowerCase()),
      );

      return {
        'success': true,
        'contacts': rows,
        'count': rows.length,
      };
    } catch (e) {
      return {
        'success': false,
        'error': e.toString(),
        'contacts': <Map<String, dynamic>>[],
      };
    }
  }
}

class _ContactMultiSelectSheet extends StatefulWidget {
  const _ContactMultiSelectSheet({required this.contacts});

  final List<Contact> contacts;

  @override
  State<_ContactMultiSelectSheet> createState() =>
      _ContactMultiSelectSheetState();
}

class _ContactMultiSelectSheetState extends State<_ContactMultiSelectSheet> {
  late final Set<String> _selectedIds;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _selectedIds = {};
  }

  List<Contact> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.contacts;
    return widget.contacts.where((c) {
      final hay = [
        c.displayName,
        ...c.phones.map((p) => p.number),
        ...c.emails.map((e) => e.address),
      ].join(' ').toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  void _toggleAllVisible(bool select) {
    final ids = _filtered.map((c) => c.id);
    setState(() {
      if (select) {
        _selectedIds.addAll(ids);
      } else {
        _selectedIds.removeAll(ids);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    final selectedCount = _selectedIds.length;
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final screenHeight = MediaQuery.sizeOf(context).height;
    final height = (screenHeight * 0.88 - viewInsets.bottom).clamp(280.0, screenHeight * 0.92);

    return Padding(
      padding: EdgeInsets.only(bottom: viewInsets.bottom),
      child: SizedBox(
      height: height,
      child: Column(
        children: [
          const SizedBox(height: 8),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: Colors.black26,
              borderRadius: BorderRadius.circular(99),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 8),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Importer des contacts',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Annuler'),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Rechercher…',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                fillColor: const Color(0xFFF5F5F5),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
              style: const TextStyle(fontSize: 16),
              onChanged: (v) => setState(() => _query = v),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
            child: Row(
              children: [
                TextButton(
                  onPressed: () => _toggleAllVisible(true),
                  child: const Text('Tout sélectionner'),
                ),
                TextButton(
                  onPressed: () => _toggleAllVisible(false),
                  child: const Text('Tout désélectionner'),
                ),
                const Spacer(),
                Text(
                  '$selectedCount sélectionné(s)',
                  style: const TextStyle(color: Colors.black54, fontSize: 13),
                ),
                const SizedBox(width: 8),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: filtered.isEmpty
                ? const Center(child: Text('Aucun contact trouvé'))
                : ListView.builder(
                    itemCount: filtered.length,
                    itemBuilder: (context, index) {
                      final c = filtered[index];
                      final selected = _selectedIds.contains(c.id);
                      final phone =
                          c.phones.isNotEmpty ? c.phones.first.number : '';
                      return CheckboxListTile(
                        value: selected,
                        activeColor: kRosePrincipal,
                        title: Text(
                          c.displayName.isNotEmpty ? c.displayName : 'Sans nom',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        subtitle: Text(phone),
                        onChanged: (v) {
                          setState(() {
                            if (v == true) {
                              _selectedIds.add(c.id);
                            } else {
                              _selectedIds.remove(c.id);
                            }
                          });
                        },
                      );
                    },
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: selectedCount == 0
                      ? null
                      : () {
                          final chosen = widget.contacts
                              .where((c) => _selectedIds.contains(c.id))
                              .toList();
                          Navigator.of(context).pop(chosen);
                        },
                  style: FilledButton.styleFrom(
                    backgroundColor: kRosePrincipal,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: Text('Importer ($selectedCount)'),
                ),
              ),
            ),
          ),
        ],
      ),
      ),
    );
  }
}
