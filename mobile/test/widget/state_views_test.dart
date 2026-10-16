import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:webintoapp_mobile/core/widgets/empty_view.dart';
import 'package:webintoapp_mobile/core/widgets/error_view.dart';
import 'package:webintoapp_mobile/core/widgets/loading_view.dart';

void main() {
  group('Standard UI State Views Widget Tests', () {
    testWidgets('LoadingView renders progress indicator and message', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: LoadingView(message: 'Fetching catalog products...'),
          ),
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Fetching catalog products...'), findsOneWidget);
    });

    testWidgets('ErrorView renders error message and triggers onRetry', (tester) async {
      bool retryClicked = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ErrorView(
              title: 'Server Timeout',
              message: 'Failed to contact store backend.',
              onRetry: () => retryClicked = true,
            ),
          ),
        ),
      );

      expect(find.text('Server Timeout'), findsOneWidget);
      expect(find.text('Failed to contact store backend.'), findsOneWidget);

      await tester.tap(find.text('Try Again'));
      await tester.pump();

      expect(retryClicked, isTrue);
    });

    testWidgets('EmptyView renders title, message, and triggers action button', (tester) async {
      bool actionClicked = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: EmptyView(
              title: 'Empty Cart',
              message: 'Your cart has no items.',
              actionLabel: 'Browse Items',
              onAction: () => actionClicked = true,
            ),
          ),
        ),
      );

      expect(find.text('Empty Cart'), findsOneWidget);
      expect(find.text('Your cart has no items.'), findsOneWidget);

      await tester.tap(find.text('Browse Items'));
      await tester.pump();

      expect(actionClicked, isTrue);
    });
  });
}
